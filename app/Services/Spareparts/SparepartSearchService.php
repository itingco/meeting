<?php

declare(strict_types=1);

namespace App\Services\Spareparts;

use Throwable;

final class SparepartSearchService
{
    public function __construct(
        private readonly SparepartSimilarityRepository $similarity,
        private readonly SparepartInventoryRepository $inventory,
        private readonly SparepartRecommendationEngine $engine,
    ) {
    }

    /** @return array<string,mixed> */
    public function search(string $code, string $database = 'all'): array
    {
        $code = SparepartCode::normalize($code);
        $sources = match (strtolower($database)) {
            'ingco' => ['INGCO'],
            'smi' => ['SMI'],
            default => ['INGCO', 'SMI'],
        };
        $errors = [];
        $alternatives = [];
        $similarityFailed = false;

        try {
            $alternatives = $this->similarity->alternatives($code);
        } catch (Throwable $e) {
            report($e);
            $similarityFailed = true;
            $errors[] = 'Tidak dapat membaca tabel Similar Items. Periksa koneksi DB_AppHub dan PRODUCT_STRATEGY_SIMILAR_TABLE.';
        }

        $partRows = $this->inventoryRows([$code, ...array_keys($alternatives)], $sources, $errors);
        $decision = $this->engine->evaluate($code, $alternatives, $partRows);
        $donors = [];
        $canSuggestDonors = $decision['priority'] === 'donor' && !$similarityFailed && $errors === [];
        if ($canSuggestDonors && $decision['parents'] !== []) {
            $donorRows = $this->inventoryRows(array_keys($decision['parents']), $sources, $errors);
            foreach ($decision['parents'] as $parentCode => $partCodes) {
                $donor = $this->engine->item($parentCode, $donorRows);
                $donor['for_spareparts'] = $partCodes;
                $donors[] = $donor;
            }
            usort($donors, static fn (array $a, array $b): int =>
                [-$a['total_qty'], (string) $a['item_code']]
                <=> [-$b['total_qty'], (string) $b['item_code']]
            );
        }

        $priority = $errors !== [] ? 'incomplete' : $decision['priority'];
        if ($priority === 'donor') {
            $priority = count(array_filter($donors, static fn (array $r): bool => $r['total_qty'] > 0)) > 0
                ? 'donor' : 'none';
        }

        return [
            'code' => $code,
            'sources' => $sources,
            'original' => $decision['original'],
            'similar' => $decision['similar'],
            'donors' => $donors,
            'priority' => $priority,
            'errors' => $errors,
            'similarity_failed' => $similarityFailed,
            'searched_at' => now()->format('d-m-Y H:i:s'),
        ];
    }

    /**
     * @param list<string> $codes
     * @param list<string> $sources
     * @param list<string> $errors
     * @return list<array<string,mixed>>
     */
    private function inventoryRows(array $codes, array $sources, array &$errors): array
    {
        $rows = [];
        foreach ($sources as $source) {
            try {
                foreach ($this->inventory->fetch($codes, $source) as $row) {
                    $rows[] = $row;
                }
            } catch (Throwable $e) {
                report($e);
                $errors[] = "Stok AS_{$source} gagal dibaca dengan perhitungan stok ERP yang sudah ada (USP_IC_Items_Calculate). Lihat storage/logs/laravel.log untuk penyebabnya.";
            }
        }

        return $rows;
    }
}
