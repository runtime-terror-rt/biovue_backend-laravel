<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SupplierWeeklySummaryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $supplierName;
    public $reportPeriod;
    public $stats;
    public $topProducts;
    public $recentInquiries;
    public $dashboardUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(
        string $supplierName,
        string $reportPeriod,
        array $stats,
        array $topProducts = [],
        array $recentInquiries = [],
        string $dashboardUrl = 'https://biovuedigitalwellness.com/supplier-dashboard'
    ) {
        $this->supplierName = $supplierName;
        $this->reportPeriod = $reportPeriod;
        $this->stats = $stats;
        $this->topProducts = $topProducts;
        $this->recentInquiries = $recentInquiries;
        $this->dashboardUrl = $dashboardUrl;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject("Your BioVue Weekly Performance Summary ({$this->reportPeriod})")
                    ->view('emails.supplier_weekly_summary');
    }
}
