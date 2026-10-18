<?php

declare(strict_types=1);

return [
    // Price1 in IC_ItemPriceList; level 1 is typically retail/HET, verify in ERP.
    'retail_price_level_id' => (int) env('SPAREPART_RETAIL_PRICE_LEVEL_ID', 1),
    // Native ERP stock procedure USP_IC_Items_Calculate is reused directly.
    // SPAREPART_STOCK_PROCEDURE and custom USP_Dashboard_SparepartStock are NOT used.
    'similar_depth' => (int) env('SPAREPART_SIMILAR_DEPTH', 2),
    'max_alternatives' => (int) env('SPAREPART_MAX_ALTERNATIVES', 80),
];
