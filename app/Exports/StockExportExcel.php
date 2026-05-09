<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class StockExportExcel implements FromView
{
    protected $filters;

    protected $summary;

    /**
     * Create a new export instance.
     *
     * @param  mixed  $filters
     * @param  mixed  $summary
     */
    public function __construct($filters, $summary)
    {
        $this->filters = $filters;
        $this->summary = $summary;
    }

    /**
     * Render the view for the export.
     */
    public function view(): View
    {
        return view('exports.stock-report.export-excel', [
            'filters' => $this->filters,
            'filteredStocks' => $this->summary,
        ]);
    }
}
