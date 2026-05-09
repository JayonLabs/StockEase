<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class PurchaseExportExcel implements FromView
{
    protected $purchase;

    protected $filters;

    protected $summary;

    /**
     * Create a new export instance.
     *
     * @param  mixed  $purchase
     * @param  mixed  $filters
     * @param  mixed  $summary
     */
    public function __construct($purchase, $filters, $summary)
    {
        $this->purchase = $purchase;
        $this->filters = $filters;
        $this->summary = $summary;
    }

    /**
     * Render the view for the export.
     */
    public function view(): View
    {
        return view('exports.purchase-report.export-excel', [
            'purchase' => $this->purchase,
            'filters' => $this->filters,
            'summary' => $this->summary,
        ]);
    }
}
