<?php

declare(strict_types=1);

namespace App\Services\Spareparts;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reads stock with ERP's EXISTING USP_IC_Items_Calculate.
 * Mirrors the mechanism used by dbo.USP_Dashboard_SisaStokWarehouse in Stock Aging,
 * but narrows #SelectedItems by requested codes instead of the BDG category.
 * Nothing is created permanently; all #tables are SQL Server session-local.
 */
final class SparepartInventoryRepository
{
    /**
     * @param list<string> $codes
     * @return list<array<string,mixed>> Also includes zero-stock master-item metadata.
     */
    public function fetch(array $codes, string $source): array
    {
        $connections = ['INGCO' => 'sqlsrv_ingco', 'SMI' => 'sqlsrv_smi'];
        $source = strtoupper(trim($source));
        if (!isset($connections[$source])) {
            throw new InvalidArgumentException('Sumber ERP tidak dikenal.');
        }

        $codes = array_values(array_unique(array_filter(array_map(
            static fn (string $code): string => SparepartCode::normalize($code),
            $codes,
        ))));
        foreach ($codes as $code) {
            if (preg_match('/^[A-Z0-9._-]{1,100}$/D', $code) !== 1) {
                throw new InvalidArgumentException('Kode barang tidak valid: '.$code);
            }
        }
        if ($codes === []) {
            return [];
        }

        $level = (int) config('sparepart_search.retail_price_level_id', 1);
        if ($level < 1) {
            throw new InvalidArgumentException('Price level retail tidak valid.');
        }

        $connection = DB::connection($connections[$source]);
        $result = [];

        // One stock calculation for up to 80 requested codes per source.
        foreach (array_chunk($codes, 80) as $chunk) {
            foreach ($this->fetchChunk($connection, $chunk, $level) as $row) {
                $result[] = [
                    'source' => $source,
                    'item_code' => SparepartCode::normalize((string) ($row->ItemCode ?? '')),
                    'item_name' => trim((string) ($row->ItemName ?? '')),
                    'warehouse_id' => isset($row->WarehouseID) ? (int) $row->WarehouseID : null,
                    'warehouse_code' => trim((string) ($row->WarehouseCode ?? '')),
                    'warehouse_name' => trim((string) ($row->WarehouseName ?? '')),
                    'qty' => (float) ($row->OnHandQty ?? 0),
                    'retail_price' => isset($row->RetailPrice) ? (float) $row->RetailPrice : null,
                ];
            }
        }

        return $result;
    }

    /** @param list<string> $codes @return list<object> */
    private function fetchChunk(ConnectionInterface $db, array $codes, int $level): array
    {
        // Transaction keeps all temporary tables on the SAME SQL Server connection.
        // Every statement uses parameter binding; no item code is interpolated.
        return $db->transaction(static function () use ($db, $codes, $level): array {
            // PDO_SQLSRV: use unprepared() for #table DDL to keep the tables
            // in the connection/session scope (not an sp_prepexec scope).
            $db->unprepared('SET NOCOUNT ON');
            $db->unprepared('CREATE TABLE #SelectedItems (ItemID INT NOT NULL PRIMARY KEY)');
            $db->unprepared('CREATE TABLE #SelectedWarehouses (WarehouseID INT NOT NULL PRIMARY KEY)');
            $db->unprepared('CREATE TABLE #ItemBalancePerLocation (ItemID INT, WarehouseID INT, UOMLevel TINYINT, Balance MONEY)');
            $db->unprepared('CREATE INDEX IX_SparepartLocation ON #ItemBalancePerLocation (ItemID, WarehouseID, UOMLevel)');

            $placeholders = implode(', ', array_fill(0, count($codes), '?'));
            $db->insert(
                "INSERT INTO #SelectedItems (ItemID)
                 SELECT I.ItemID
                 FROM dbo.IC_Items AS I
                 WHERE UPPER(LTRIM(RTRIM(I.ItemCode))) IN ({$placeholders})",
                $codes,
            );
            $db->insert(
                'INSERT INTO #SelectedWarehouses (WarehouseID) SELECT WarehouseID FROM dbo.IC_Warehouses',
            );

            // Avoid running the expensive ERP calculator if no codes exist.
            if ((int) $db->selectOne('SELECT COUNT(*) AS Total FROM #SelectedItems')->Total === 0) {
                $db->unprepared('DROP TABLE #ItemBalancePerLocation');
                $db->unprepared('DROP TABLE #SelectedWarehouses');
                $db->unprepared('DROP TABLE #SelectedItems');
                return [];
            }

            // Exact existing ERP stock calculation contract also used by Stock Aging.
            // @AllItems=0 reads #SelectedItems; no additional permanent USP is required.
            $db->statement(
                "EXEC dbo.USP_IC_Items_Calculate
                    @AllItems = 0,
                    @PerDate = ?,
                    @OutputKind = 'PerLocationBalanceReports',
                    @OutputTable = '#ItemBalancePerLocation',
                    @ExtraJoinCondition = 'JOIN #SelectedWarehouses SWH ON SWH.WarehouseID = \$[OutletField]',
                    @IncludeBroken = 1,
                    @IncludeConsignmentOutlets = 1,
                    @GoodStockOnly = NULL",
                [now()->toDateString()],
            );

            // Report even items without stock. ERP stock is normalized to smallest UOM,
            // while Price1 is the selected ERP retail price level (may have another UOM).
            // No BDG filter: spareparts AND donor item codes can be searched.
            $rows = $db->select(
                "WITH Stock AS (
                    SELECT B.ItemID, B.WarehouseID,
                           SUM(dbo.UDF_Common_ConvertToSmallestUOMEx(
                               I.Packing2, I.Packing3, I.Packing4, B.Balance, B.UOMLevel
                           )) AS OnHandQty
                    FROM #ItemBalancePerLocation AS B
                    JOIN dbo.IC_Items AS I ON I.ItemID = B.ItemID
                    GROUP BY B.ItemID, B.WarehouseID
                )
                SELECT I.ItemCode AS ItemCode,
                       I.ItemName AS ItemName,
                       W.WarehouseID AS WarehouseID,
                       W.WarehouseCode AS WarehouseCode,
                       W.Name AS WarehouseName,
                       CAST(COALESCE(S.OnHandQty, 0) AS DECIMAL(28,4)) AS OnHandQty,
                       CAST(P.Price1 AS DECIMAL(28,4)) AS RetailPrice
                FROM #SelectedItems AS SI
                JOIN dbo.IC_Items AS I ON I.ItemID = SI.ItemID
                LEFT JOIN Stock AS S ON S.ItemID = I.ItemID AND S.OnHandQty <> 0
                LEFT JOIN dbo.IC_Warehouses AS W ON W.WarehouseID = S.WarehouseID
                OUTER APPLY (
                    SELECT TOP (1) IPL.Price1
                    FROM dbo.IC_ItemPriceList AS IPL
                    WHERE IPL.ItemID = I.ItemID AND IPL.PriceLevelID = ?
                ) AS P
                ORDER BY I.ItemCode, W.WarehouseCode",
                [$level],
            );
            // Remove the session-local tables so a second search on this PDO connection works.
            $db->unprepared('DROP TABLE #ItemBalancePerLocation');
            $db->unprepared('DROP TABLE #SelectedWarehouses');
            $db->unprepared('DROP TABLE #SelectedItems');

            return $rows;
        });
    }
}
