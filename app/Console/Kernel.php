<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
       	'App\Console\Commands\ScanAmazonOrders',
        'App\Console\Commands\ScanAmazonMcfOrders',
        'App\Console\Commands\ScanAmazonAsins',
        'App\Console\Commands\ScanAmazonStock',
        'App\Console\Commands\ScanAmazonFinances',
        'App\Console\Commands\ScanAmazonReturn',
        'App\Console\Commands\ScanAmazonSkus',
		'App\Console\Commands\ScanGoogleTrends',
		'App\Console\Commands\ScanPaymentsHistory',
        'App\Console\Commands\SyncSapAsin',
        'App\Console\Commands\CalculateDailyData',
        'App\Console\Commands\SymmetryAsins',
		'App\Console\Commands\SyncSapRate',
		'App\Console\Commands\SyncSapSku',
		'App\Console\Commands\SyncSapSkuSite',
		'App\Console\Commands\SyncSapShipment',
		'App\Console\Commands\SyncSapPurchase',
		'App\Console\Commands\CalAsinInventory',
		'App\Console\Commands\SyncSapPurchaseRecord',
		'App\Console\Commands\CalculateMrp',
        'App\Console\Commands\ScanAmazonShipments',
        'App\Console\Commands\ScanSettlement',
        'App\Console\Commands\InsertOrderToSap',
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();
		$ordersLogPath             = env( 'OrdersLogPath', '/var/log/orders.log' );
        $stockLogPath              = env( 'StockLogPath', '/var/log/stock.log' );
        $mcfLogPath            = env( 'McfLogPath', '/var/log/mcforders.log' );
        $financesLogPath       = env( 'FinancesLogPath', '/var/log/finances.log' );
		$shipmentsLogPath       = env( 'ShipmentsLogPath', '/var/log/shipments.log' );
		$reportLogPath       = env( 'ReportLogPath', '/var/log/report.log' );
		$keepaLogPath       = env( 'KeepaLogPath', '/var/log/keepa.log' );
		$RequestLogPath       = env( 'RequestLogPath', '/var/log/request.log' );
		$PaypalLogPath       = env( 'PaypalLogPath', '/var/log/paypal.log' );
        $SapDataSyncLogPath = env( 'SapDataSyncLogPath', '/var/log/syncsap.log');
		$GoogleTrendsLogPath = env( 'GoogleTrendsLogPath', '/var/log/ggtrends.log');
        $SymAsinsLogPath = env( 'SymAsinsLogPath', '/var/log/symasins.log');
        $DailyDataLogPath = env( 'DailyDataLogPath', '/var/log/dailydata.log');
		
		$schedule->command('scan:ggtrends')->weekly()->sundays()->at('8:30')->name('GOOGLE_TRENDS')->sendOutputTo($GoogleTrendsLogPath)->withoutOverlapping();
        $schedule->command('sym:asins')->weekly()->mondays()->at('0:30')->name('SYM_ASINS')->sendOutputTo($SymAsinsLogPath)->withoutOverlapping();
		
		$schedule->command('scan:orders')->cron('*/20 * * * *')->name('GET_ORDERS')->sendOutputTo($ordersLogPath)->withoutOverlapping();
        $schedule->command('scan:orderItems')->everyMinute()->name('GET_ORDERITEMS')->withoutOverlapping();
        
		$schedule->command('scan:stock')->twiceDaily(2, 6)->name('GET_STOCK')->sendOutputTo($stockLogPath)->withoutOverlapping();
		
		$schedule->command('scan:mcforders')->twiceDaily(3, 8)->name('GET_MCFORDERS')->sendOutputTo($mcfLogPath)->withoutOverlapping();
		
		$schedule->command('scan:finances')->dailyAt('5:00')->name('GET_FINANCES')->sendOutputTo($financesLogPath)->withoutOverlapping();
		
		$schedule->command('scan:shipments')->cron('0 */2 * * *')->name('GET_SHIPMENTS')->sendOutputTo($shipmentsLogPath)->withoutOverlapping();
		
        $schedule->command('request:report --type=_GET_FBA_MYI_ALL_INVENTORY_DATA_')->cron('0 */4 * * *')->name('GET_INVENTORYS')->sendOutputTo($RequestLogPath)->withoutOverlapping();
        $schedule->command('request:report --type=_GET_AFN_INVENTORY_DATA_BY_COUNTRY_')->cron('5 */4 * * *')->name('GET_INVENTORY_COUNTRY')->sendOutputTo($RequestLogPath)->withoutOverlapping();
        $schedule->command('request:report --type=_GET_AFN_INVENTORY_DATA_')->cron('10 */6 * * *')->name('GET_INVENTORY_DATA')->sendOutputTo($RequestLogPath)->withoutOverlapping();
        $schedule->command('request:report --type=_GET_FBA_FULFILLMENT_INVENTORY_SUMMARY_DATA_ --afterDate='.date("Y-m-d",strtotime('-2day')).'T00:00:00 --beforeDate='.date("Y-m-d",strtotime('-1day')).'T23:59:59')->dailyAt('9:00')->name('GET_INVENTORY_SUMMARY')->sendOutputTo($RequestLogPath)->withoutOverlapping();
        $schedule->command('request:report --type=_GET_FBA_FULFILLMENT_INVENTORY_SUMMARY_DATA_ --afterDate='.date("Y-m-d",strtotime('-2day')).'T00:00:00 --beforeDate='.date("Y-m-d",strtotime('-1day')).'T23:59:59')->dailyAt('15:00')->name('GET_INVENTORY_SUMMARY')->sendOutputTo($RequestLogPath)->withoutOverlapping();

        $schedule->command('request:report --type=_GET_FBA_INVENTORY_AGED_DATA_')->dailyAt('10:01')->name('GET_INVENTORY_AGE')->sendOutputTo($RequestLogPath)->withoutOverlapping();
        $schedule->command('request:report --type=_GET_FBA_FULFILLMENT_CURRENT_INVENTORY_DATA_ --afterDate='.date("Y-m-d",strtotime('-2day')).'T00:00:00 --beforeDate='.date("Y-m-d",strtotime('-1day')).'T23:59:59')->dailyAt('10:02')->name('_GET_FBA_FULFILLMENT_CURRENT_INVENTORY_DATA_')->sendOutputTo($RequestLogPath)->withoutOverlapping();
        $schedule->command('request:report --type=_GET_FBA_FULFILLMENT_MONTHLY_INVENTORY_DATA_  --afterDate='.date("Y-m",strtotime('-2month')).'-01T00:00:00 --beforeDate='.date("Y-m-d",strtotime('-1day')).'T23:59:59')->monthlyOn(4, '10:03')->name('_GET_FBA_FULFILLMENT_MONTHLY_INVENTORY_DATA_')->sendOutputTo($RequestLogPath)->withoutOverlapping();
        $schedule->command('request:report --type=_GET_FBA_FULFILLMENT_INVENTORY_RECEIPTS_DATA_ --afterDate='.date("Y-m-d",strtotime('-2day')).'T00:00:00 --beforeDate='.date("Y-m-d",strtotime('-1day')).'T23:59:59')->dailyAt('10:04')->name('_GET_FBA_FULFILLMENT_INVENTORY_RECEIPTS_DATA_')->sendOutputTo($RequestLogPath)->withoutOverlapping();
        $schedule->command('request:report --type=_GET_RESERVED_INVENTORY_DATA_')->dailyAt('10:05')->name('_GET_RESERVED_INVENTORY_DATA_')->sendOutputTo($RequestLogPath)->withoutOverlapping();
        $schedule->command('request:report --type=_GET_FBA_FULFILLMENT_INVENTORY_ADJUSTMENTS_DATA_ --afterDate='.date("Y-m-d",strtotime('-2day')).'T00:00:00 --beforeDate='.date("Y-m-d",strtotime('-1day')).'T23:59:59')->dailyAt('10:06')->name('_GET_FBA_FULFILLMENT_INVENTORY_ADJUSTMENTS_DATA_')->sendOutputTo($RequestLogPath)->withoutOverlapping();
        $schedule->command('request:report --type=_GET_FBA_FULFILLMENT_INVENTORY_HEALTH_DATA_')->dailyAt('10:07')->name('_GET_FBA_FULFILLMENT_INVENTORY_HEALTH_DATA_')->sendOutputTo($RequestLogPath)->withoutOverlapping();
        $schedule->command('request:report --type=_GET_FBA_MYI_UNSUPPRESSED_INVENTORY_DATA_')->dailyAt('10:08')->name('_GET_FBA_MYI_UNSUPPRESSED_INVENTORY_DATA_')->sendOutputTo($RequestLogPath)->withoutOverlapping();
        $schedule->command('request:report --type=_GET_RESTOCK_INVENTORY_RECOMMENDATIONS_REPORT_')->dailyAt('10:09')->name('_GET_RESTOCK_INVENTORY_RECOMMENDATIONS_REPORT_')->sendOutputTo($RequestLogPath)->withoutOverlapping();
        $schedule->command('request:report --type=_GET_FBA_FULFILLMENT_INBOUND_NONCOMPLIANCE_DATA_ --afterDate='.date("Y-m-d",strtotime('-2day')).'T00:00:00 --beforeDate='.date("Y-m-d",strtotime('-1day')).'T23:59:59')->dailyAt('10:10')->name('_GET_FBA_FULFILLMENT_INBOUND_NONCOMPLIANCE_DATA_')->sendOutputTo($RequestLogPath)->withoutOverlapping();
        $schedule->command('request:report --type=_GET_STRANDED_INVENTORY_UI_DATA_')->dailyAt('10:11')->name('_GET_STRANDED_INVENTORY_UI_DATA_')->sendOutputTo($RequestLogPath)->withoutOverlapping();
        $schedule->command('request:report --type=_GET_STRANDED_INVENTORY_LOADER_DATA_')->dailyAt('10:12')->name('_GET_STRANDED_INVENTORY_LOADER_DATA_')->sendOutputTo($RequestLogPath)->withoutOverlapping();

        $schedule->command('request:settlement')->dailyAt('10:13')->name('_GET_SETTLEMENT_')->sendOutputTo($RequestLogPath)->withoutOverlapping();

		$schedule->command('request:report --type=_GET_FBA_FULFILLMENT_CUSTOMER_RETURNS_DATA_ --afterDate='.date('Y-m-d',strtotime('-2day')).' --beforeDate='.date('Y-m-d'))->dailyAt('4:30')->name('GET_RETURNS')->sendOutputTo($RequestLogPath)->withoutOverlapping();
		$schedule->command('scan:asins')->everyMinute()->name('GET_ASINS')->sendOutputTo($keepaLogPath)->withoutOverlapping();
		$schedule->command('get:report')->everyFiveMinutes()->name('GET_REPORTS')->sendOutputTo($reportLogPath)->withoutOverlapping();
		$schedule->command('scan:payments')->hourly()->name('GET_PAYPAL_PAYMENTS')->sendOutputTo($PaypalLogPath)->withoutOverlapping();
        $schedule->command('sync:asins')->everyTenMinutes()->name('SYNC_ASINS')->sendOutputTo($SapDataSyncLogPath)->withoutOverlapping();//->twiceDaily(1, 5)
		$schedule->command('sync:skusite')->dailyAt('0:01')->name('SYNC_SKUSITES')->sendOutputTo($SapDataSyncLogPath)->withoutOverlapping();
		$schedule->command('sync:skus --afterDate='.date('Y-m-d',strtotime('-1day')).' --beforeDate='.date('Y-m-d',strtotime('+1day')))->twiceDaily(11, 23)->name('SYNC_SKUS')->sendOutputTo($SapDataSyncLogPath)->withoutOverlapping();
		
		$schedule->command('sync:purchaserecords --afterDate='.date('Y-m-d',strtotime('-1day')).' --beforeDate='.date('Y-m-d',strtotime('+1day')))->twiceDaily(1, 5)->name('SYNC_PURCHASE_RECORDS')->sendOutputTo($SapDataSyncLogPath)->withoutOverlapping();
		
        $schedule->command('cal:dailydata')->dailyAt('7:00')->name('CAL_DAILY')->sendOutputTo($DailyDataLogPath)->withoutOverlapping();
		$schedule->command('sync:rates')->dailyAt('4:00')->name('SYNC_RATES')->withoutOverlapping();
		$schedule->command('cal:asinInventory')->hourly()->name('CAL_ASININVENTORY')->withoutOverlapping();
        $schedule->command('push:order')->hourly()->name('OrderToSap')->withoutOverlapping();
        
		
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
