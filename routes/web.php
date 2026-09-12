<?php

use App\Http\Controllers\Admin\CommercialController;
use App\Http\Controllers\Admin\ComptabiliteController;
use App\Http\Controllers\Admin\AnnualReportController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DemoRequestController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EntrepriseController;
use App\Http\Controllers\Admin\GlobalSearchController;
use App\Http\Controllers\Admin\HubController;
use App\Http\Controllers\Admin\AdministrationController;
use App\Http\Controllers\Admin\RhController;
use App\Http\Controllers\Admin\AiAssistantController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SuccursaleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\QrAttendanceController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\SuperAdminConsoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect(auth()->user()->hasRole('super_admin') ? '/console' : '/admin/dashboard');
    }

    return app(\App\Http\Controllers\LandingController::class)->index();
});

Route::get('/landing', [App\Http\Controllers\LandingController::class, 'index'])->name('landing');
Route::post('/demo/request', [App\Http\Controllers\LandingController::class, 'requestDemo'])->name('demo.request');
Route::post('/pack/request', [App\Http\Controllers\LandingController::class, 'requestPack'])->name('pack.request');
Route::get('/demo', [App\Http\Controllers\LandingController::class, 'demo'])->name('demo');
Route::post('/demo/logout', [App\Http\Controllers\LandingController::class, 'leaveDemo'])->name('demo.logout');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post');
});

Route::get('/attendance/qr/scan', [QrAttendanceController::class, 'scan'])->name('attendance.qr.scan');
Route::post('/attendance/qr/process', [QrAttendanceController::class, 'process'])->name('attendance.qr.process');

Route::middleware(['auth', 'super_admin', 'tenant'])->prefix('console')->group(function () {
    Route::get('/', [SuperAdminConsoleController::class, 'index'])->name('console.index');
    Route::get('/dashboard', [SuperAdminConsoleController::class, 'index'])->name('console.dashboard');
    Route::get('/landing', [SuperAdminConsoleController::class, 'landingSettings'])->name('console.landing');
    Route::post('/landing', [SuperAdminConsoleController::class, 'updateLandingSettings'])->name('console.landing.update');
    Route::get('/demandes-packs', [SuperAdminConsoleController::class, 'packRequests'])->name('console.pack-requests');
    Route::post('/demandes-packs/{packRequest}/email', [SuperAdminConsoleController::class, 'replyPackRequestByEmail'])->name('console.pack-requests.email');
    Route::get('/suivi-comptes', [SuperAdminConsoleController::class, 'accountTracking'])->name('console.account-tracking');
    Route::get('/entreprises/create', [SuperAdminConsoleController::class, 'createEntreprise'])->name('console.entreprises.create');
    Route::post('/entreprises', [SuperAdminConsoleController::class, 'storeEntreprise'])->name('console.entreprises.store');
    Route::get('/entreprises/{entreprise}', [SuperAdminConsoleController::class, 'showEntreprise'])->name('console.entreprises.show');
    Route::post('/administrateurs/{user}/impersonate', [SuperAdminConsoleController::class, 'impersonateAdmin'])->name('console.impersonate');
    Route::get('/entreprises/{entreprise}/edit', [SuperAdminConsoleController::class, 'editEntreprise'])->name('console.entreprises.edit');
    Route::put('/entreprises/{entreprise}', [SuperAdminConsoleController::class, 'updateEntreprise'])->name('console.entreprises.update');
    Route::patch('/entreprises/{entreprise}/renew', [SuperAdminConsoleController::class, 'renewEntreprise'])->name('console.entreprises.renew');
    Route::patch('/entreprises/{entreprise}/toggle', [SuperAdminConsoleController::class, 'toggleEntreprise'])->name('console.entreprises.toggle');
});

Route::middleware('auth')->post('/impersonation/stop', [SuperAdminConsoleController::class, 'stopImpersonation'])->name('console.impersonation.stop');
Route::match(['get', 'post'], '/setting/general/renew/callback', [SettingController::class, 'handleRenewalCallback'])->name('admin.settings.general.renew.callback');

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::match(['get', 'post'], '/locale', [AuthController::class, 'setLocale'])->name('locale.update');
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/assistant-ia', [AiAssistantController::class, 'index'])->name('admin.ai-assistant');
        Route::post('/assistant-ia', [AiAssistantController::class, 'ask'])->name('admin.ai-assistant.ask');
        Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/hub', [HubController::class, 'index'])->name('admin.hub');
        Route::view('/design-system', 'admin.design-system', ['title' => 'Design System'])->name('admin.design-system');
        Route::get('/recherche', GlobalSearchController::class)->name('admin.search');
        Route::get('/administration', [AdministrationController::class, 'index'])->name('admin.administration');
        Route::get('/administration/visiteurs', [AdministrationController::class, 'visitors'])->name('admin.administration.visitors');
        Route::post('/administration/visiteurs', [AdministrationController::class, 'storeVisitor'])->name('admin.administration.visitors.store');
        Route::put('/administration/visiteurs/{visitor}', [AdministrationController::class, 'updateVisitor'])->name('admin.administration.visitors.update');
        Route::post('/administration/visiteurs/{visitor}/presence', [AdministrationController::class, 'visitorPresence'])->name('admin.administration.visitors.presence');
        Route::delete('/administration/visiteurs/{visitor}', [AdministrationController::class, 'destroyVisitor'])->name('admin.administration.visitors.destroy');
        Route::get('/administration/appels', [AdministrationController::class, 'calls'])->name('admin.administration.calls');
        Route::post('/administration/appels', [AdministrationController::class, 'storeCall'])->name('admin.administration.calls.store');
        Route::put('/administration/appels/{call}', [AdministrationController::class, 'updateCall'])->name('admin.administration.calls.update');
        Route::post('/administration/appels/{call}/suite', [AdministrationController::class, 'callStatus'])->name('admin.administration.calls.status');
        Route::delete('/administration/appels/{call}', [AdministrationController::class, 'destroyCall'])->name('admin.administration.calls.destroy');
        Route::get('/administration/courriers', [AdministrationController::class, 'correspondences'])->name('admin.administration.correspondences');
        Route::post('/administration/courriers', [AdministrationController::class, 'storeCorrespondence'])->name('admin.administration.correspondences.store');
        Route::put('/administration/courriers/{correspondence}', [AdministrationController::class, 'updateCorrespondence'])->name('admin.administration.correspondences.update');
        Route::post('/administration/courriers/{correspondence}/suivi', [AdministrationController::class, 'correspondenceStatus'])->name('admin.administration.correspondences.status');
        Route::delete('/administration/courriers/{correspondence}', [AdministrationController::class, 'destroyCorrespondence'])->name('admin.administration.correspondences.destroy');
        Route::get('/administration/reunions', [AdministrationController::class, 'meetings'])->name('admin.administration.meetings');
        Route::post('/administration/reunions', [AdministrationController::class, 'storeMeeting'])->name('admin.administration.meetings.store');
        Route::put('/administration/reunions/{meeting}', [AdministrationController::class, 'updateMeeting'])->name('admin.administration.meetings.update');
        Route::post('/administration/reunions/{meeting}/suite', [AdministrationController::class, 'meetingStatus'])->name('admin.administration.meetings.status');
        Route::post('/administration/reunions/{meeting}/compte-rendu', [AdministrationController::class, 'meetingMinutes'])->name('admin.administration.meetings.minutes');
        Route::delete('/administration/reunions/{meeting}', [AdministrationController::class, 'destroyMeeting'])->name('admin.administration.meetings.destroy');
        Route::get('/administration/documents', [AdministrationController::class, 'documents'])->name('admin.administration.documents');
        Route::post('/administration/documents', [AdministrationController::class, 'storeDocument'])->name('admin.administration.documents.store');
        Route::put('/administration/documents/{document}', [AdministrationController::class, 'updateDocument'])->name('admin.administration.documents.update');
        Route::post('/administration/documents/{document}/classement', [AdministrationController::class, 'documentStatus'])->name('admin.administration.documents.status');
        Route::delete('/administration/documents/{document}', [AdministrationController::class, 'destroyDocument'])->name('admin.administration.documents.destroy');
        Route::get('/administration/{module}', [AdministrationController::class, 'module'])->name('admin.administration.module');
        Route::get('/admin/hub', [HubController::class, 'index']);
        Route::get('/comptabilite', [ComptabiliteController::class, 'index'])->name('admin.comptabilite');
        Route::get('/admin/comptabilite', [ComptabiliteController::class, 'index']);
        Route::prefix('comptabilite')->group(function () {
            Route::get('/tableau', [ComptabiliteController::class, 'tableau'])->name('admin.comptabilite.tableau');
            Route::get('/caisses', [ComptabiliteController::class, 'caisses'])->name('admin.comptabilite.caisses');
            Route::post('/caisses/accounts', [ComptabiliteController::class, 'storeAccount'])->name('admin.comptabilite.caisses.storeAccount');
            Route::put('/caisses/{cashAccount}', [ComptabiliteController::class, 'updateAccount'])->name('admin.comptabilite.caisses.updateAccount');
            Route::delete('/caisses/{cashAccount}', [ComptabiliteController::class, 'destroyAccount'])->name('admin.comptabilite.caisses.destroyAccount');
            Route::post('/caisses/{cashAccount}/toggle-status', [ComptabiliteController::class, 'toggleAccountStatus'])->name('admin.comptabilite.caisses.toggleStatus');
            Route::post('/caisses/mouvements', [ComptabiliteController::class, 'storeMovement'])->name('admin.comptabilite.caisses.storeMovement');
            Route::put('/caisses/mouvements/{cashMovement}', [ComptabiliteController::class, 'updateMovement'])->name('admin.comptabilite.caisses.updateMovement');
            Route::delete('/caisses/mouvements/{cashMovement}', [ComptabiliteController::class, 'destroyMovement'])->name('admin.comptabilite.caisses.destroyMovement');
            Route::get('/banques', [ComptabiliteController::class, 'banques'])->name('admin.comptabilite.banques');
            Route::post('/banques/accounts', [ComptabiliteController::class, 'storeBankAccount'])->name('admin.comptabilite.banques.storeAccount');
            Route::put('/banques/{bankAccount}', [ComptabiliteController::class, 'updateBankAccount'])->name('admin.comptabilite.banques.update');
            Route::delete('/banques/{bankAccount}', [ComptabiliteController::class, 'destroyBankAccount'])->name('admin.comptabilite.banques.destroy');
            Route::post('/banques/transactions', [ComptabiliteController::class, 'storeBankTransaction'])->name('admin.comptabilite.banques.storeTransaction');
            Route::get('/rapports', [ComptabiliteController::class, 'rapports'])->name('admin.comptabilite.rapports');
            Route::get('/rapport-financier', [ComptabiliteController::class, 'rapportFinancier'])->name('admin.comptabilite.rapport_financier');
            Route::post('/rapport-financier/observations', [ComptabiliteController::class, 'saveReportObservations'])->name('admin.comptabilite.rapport_financier.observations');
            Route::get('/transfer', [ComptabiliteController::class, 'transfers'])->name('admin.comptabilite.transfers');
            Route::post('/transfer', [ComptabiliteController::class, 'storeTransfer'])->name('admin.comptabilite.transfers.store');
            Route::get('/categories-depenses', [ComptabiliteController::class, 'expenseCategories'])->name('admin.comptabilite.expenseCategories');
            Route::post('/categories-depenses', [ComptabiliteController::class, 'storeExpenseCategory'])->name('admin.comptabilite.expenseCategories.store');
            Route::put('/categories-depenses/{expenseCategory}', [ComptabiliteController::class, 'updateExpenseCategory'])->name('admin.comptabilite.expenseCategories.update');
            Route::delete('/categories-depenses/{expenseCategory}', [ComptabiliteController::class, 'destroyExpenseCategory'])->name('admin.comptabilite.expenseCategories.destroy');
            Route::get('/immobilisations', [ComptabiliteController::class, 'fixedAssets'])->name('admin.comptabilite.fixedAssets');
            Route::post('/immobilisations', [ComptabiliteController::class, 'storeFixedAsset'])->name('admin.comptabilite.fixedAssets.store');
            Route::put('/immobilisations/{fixedAsset}', [ComptabiliteController::class, 'updateFixedAsset'])->name('admin.comptabilite.fixedAssets.update');
            Route::delete('/immobilisations/{fixedAsset}', [ComptabiliteController::class, 'destroyFixedAsset'])->name('admin.comptabilite.fixedAssets.destroy');
            Route::get('/factures-fournisseurs/import', [ComptabiliteController::class, 'supplierInvoices'])->name('admin.comptabilite.supplierInvoices');
            Route::post('/factures-fournisseurs/import', [ComptabiliteController::class, 'importSupplierInvoice'])->name('admin.comptabilite.supplierInvoices.import');
            Route::delete('/factures-fournisseurs', [ComptabiliteController::class, 'destroySupplierInvoices'])->name('admin.comptabilite.supplierInvoices.bulkDestroy');
            Route::delete('/factures-fournisseurs/{supplierInvoice}', [ComptabiliteController::class, 'destroySupplierInvoice'])->name('admin.comptabilite.supplierInvoices.destroy');
            Route::post('/factures-fournisseurs/{supplierInvoice}/fne', [ComptabiliteController::class, 'certifySupplierInvoice'])->name('admin.comptabilite.supplierInvoices.fne');
            Route::get('/factures-fournisseurs/{supplierInvoice}/print', [ComptabiliteController::class, 'printSupplierInvoice'])->name('admin.comptabilite.supplierInvoices.print');
            Route::get('/factures-fournisseurs/{supplierInvoice}/print-fne', [ComptabiliteController::class, 'printFneSupplierInvoice'])->name('admin.comptabilite.supplierInvoices.printFne');
            Route::get('/factures-fournisseurs/{supplierInvoice}', [ComptabiliteController::class, 'showSupplierInvoice'])->name('admin.comptabilite.supplierInvoices.show');
            Route::get('/factures-fournisseurs/{supplierInvoice}/pdf', [ComptabiliteController::class, 'pdfSupplierInvoice'])->name('admin.comptabilite.supplierInvoices.pdf');
        });
        Route::get('/rh', [RhController::class, 'index'])->name('admin.rh');
        Route::get('/rh/tableau', [RhController::class, 'tableau'])->name('admin.rh.tableau');
        Route::get('/rh/bulletins-paie', [RhController::class, 'payroll'])->name('admin.rh.payroll');
        Route::get('/rh/livre-paie', [RhController::class, 'payrollBook'])->name('admin.rh.payrollBook');
        Route::get('/rh/qr-code', [RhController::class, 'displayQr'])->name('admin.rh.qr.display');
        Route::post('/rh/qr-code/renouveler', [RhController::class, 'renewQr'])->name('admin.rh.qr.renew');
        Route::get('/rh/presences', [RhController::class, 'todayAttendance'])->name('admin.rh.attendance.today');
        Route::match(['get', 'post'], '/rh/rapport-presence', [RhController::class, 'attendanceReport'])->name('admin.rh.attendance.report');
        Route::get('/rh/bulletins-paie/create', [RhController::class, 'createPayroll'])->name('admin.rh.payroll.create');
        Route::post('/rh/bulletins-paie', [RhController::class, 'generatePayroll'])->name('admin.rh.payroll.generate');
        Route::get('/rh/bulletins-paie/{payroll}', [RhController::class, 'showPayroll'])->name('admin.rh.payroll.show');
        Route::get('/rh/bulletins-paie/{payroll}/pdf', [RhController::class, 'payrollPdf'])->name('admin.rh.payroll.pdf');
        Route::post('/rh/bulletins-paie/{payroll}/email', [RhController::class, 'emailPayroll'])->name('admin.rh.payroll.email');
        Route::post('/rh/bulletins-paie/email-bulk', [RhController::class, 'emailPayrollBulk'])->name('admin.rh.payroll.emailBulk');
        Route::get('/rh/categories-salariales', [RhController::class, 'salaryCategories'])->name('admin.rh.salaryCategories');
        Route::post('/rh/categories-salariales', [RhController::class, 'storeSalaryCategory'])->name('admin.rh.salaryCategories.store');
        Route::get('/rh/categories-salariales/{salaryCategory}/edit', [RhController::class, 'editSalaryCategory'])->name('admin.rh.salaryCategories.edit');
        Route::put('/rh/categories-salariales/{salaryCategory}', [RhController::class, 'updateSalaryCategory'])->name('admin.rh.salaryCategories.update');
        Route::delete('/rh/categories-salariales/{salaryCategory}', [RhController::class, 'destroySalaryCategory'])->name('admin.rh.salaryCategories.destroy');
        Route::get('/rh/permissions', [RhController::class, 'permissionRequests'])->name('admin.rh.permissions');
        Route::post('/rh/permissions', [RhController::class, 'storePermissionRequest'])->name('admin.rh.permissions.store');
        Route::patch('/rh/permissions/{permissionRequest}', [RhController::class, 'reviewPermissionRequest'])->name('admin.rh.permissions.review');
        Route::get('/rh/parametrage-conges', [RhController::class, 'leaveTypes'])->name('admin.rh.leaveTypes');
        Route::post('/rh/parametrage-conges', [RhController::class, 'storeLeaveType'])->name('admin.rh.leaveTypes.store');
        Route::put('/rh/parametrage-conges/{leaveType}', [RhController::class, 'updateLeaveType'])->name('admin.rh.leaveTypes.update');
        Route::delete('/rh/parametrage-conges/{leaveType}', [RhController::class, 'destroyLeaveType'])->name('admin.rh.leaveTypes.destroy');
        Route::get('/rh/conges', [RhController::class, 'leaveRequests'])->name('admin.rh.leaves');
        Route::post('/rh/conges', [RhController::class, 'storeLeaveRequest'])->name('admin.rh.leaves.store');
        Route::patch('/rh/conges/{leaveRequest}', [RhController::class, 'reviewLeaveRequest'])->name('admin.rh.leaves.review');
        Route::get('/rh/calendrier-conges', [RhController::class, 'leaveCalendar'])->name('admin.rh.leaveCalendar');
        Route::get('/rh/{module}', [RhController::class, 'module'])->name('admin.rh.module');
        Route::get('/rh/personnel/search', [EmployeeController::class, 'search'])->name('admin.rh.employees.search');
        Route::get('/rh/personnel/create', [EmployeeController::class, 'create'])->name('admin.rh.employees.create');
        Route::post('/rh/personnel', [EmployeeController::class, 'store'])->name('admin.rh.employees.store');
        Route::get('/rh/personnel/{employee}/edit', [EmployeeController::class, 'edit'])->name('admin.rh.employees.edit');
        Route::put('/rh/personnel/{employee}', [EmployeeController::class, 'update'])->name('admin.rh.employees.update');
        Route::get('/rh/personnel/{employee}', [EmployeeController::class, 'show'])->name('admin.rh.employees.show');
        Route::patch('/rh/personnel/{employee}/terminate', [EmployeeController::class, 'terminate'])->name('admin.rh.employees.terminate');
        Route::delete('/rh/personnel/{employee}', [EmployeeController::class, 'destroy'])->name('admin.rh.employees.destroy');
        Route::get('/admin/rh', [RhController::class, 'index']);
        Route::get('/commercial', [CommercialController::class, 'index'])->name('admin.commercial');
        Route::get('/commercial/tableau', [CommercialController::class, 'tableau'])->name('admin.commercial.tableau');
        Route::get('/commercial/proforma/create', [CommercialController::class, 'createProforma'])->name('admin.commercial.proforma.create');
        Route::delete('/commercial/proforma', [CommercialController::class, 'destroyProformas'])->name('admin.commercial.proforma.bulkDestroy');
        Route::get('/commercial/proforma/{proforma}/edit', [CommercialController::class, 'editProforma'])->name('admin.commercial.proforma.edit');
        Route::put('/commercial/proforma/{proforma}', [CommercialController::class, 'updateProforma'])->name('admin.commercial.proforma.update');
        Route::delete('/commercial/proforma/{proforma}', [CommercialController::class, 'destroyProforma'])->name('admin.commercial.proforma.destroy');
        Route::get('/commercial/proforma/{proforma}/print', [CommercialController::class, 'printProforma'])->name('admin.commercial.proforma.print');
        Route::post('/commercial/proforma/{proforma}/email', [CommercialController::class, 'emailProforma'])->name('admin.commercial.proforma.email');
        Route::post('/commercial/devis', [CommercialController::class, 'storeQuote'])->name('admin.commercial.quotes.store');
        Route::get('/commercial/facture-personnalisee', [CommercialController::class, 'customInvoice'])->name('admin.commercial.custom-invoice.index');
        Route::get('/commercial/facture-personnalisee/create', [CommercialController::class, 'createCustomInvoice'])->name('admin.commercial.custom-invoice.create');
        Route::post('/commercial/facture-personnalisee', [CommercialController::class, 'storeCustomInvoice'])->name('admin.commercial.custom-invoice.store');
        Route::get('/commercial/facture-personnalisee/{invoice}', [CommercialController::class, 'showCustomInvoice'])->name('admin.commercial.custom-invoice.show');
        Route::get('/commercial/facture-personnalisee/{invoice}/edit', [CommercialController::class, 'editCustomInvoice'])->name('admin.commercial.custom-invoice.edit');
        Route::put('/commercial/facture-personnalisee/{invoice}', [CommercialController::class, 'updateCustomInvoice'])->name('admin.commercial.custom-invoice.update');
        Route::delete('/commercial/facture-personnalisee/{invoice}', [CommercialController::class, 'destroyCustomInvoice'])->name('admin.commercial.custom-invoice.destroy');
        Route::post('/commercial/facture-personnalisee/{invoice}/duplicate', [CommercialController::class, 'duplicateCustomInvoice'])->name('admin.commercial.custom-invoice.duplicate');
        Route::post('/commercial/facture-personnalisee/{invoice}/emettre', [CommercialController::class, 'issueCustomInvoice'])->name('admin.commercial.custom-invoice.issue');
        Route::post('/commercial/facture-personnalisee/{invoice}/avoir', [CommercialController::class, 'creditCustomInvoice'])->name('admin.commercial.custom-invoice.credit');
        Route::post('/commercial/facture-personnalisee/{invoice}/email', [CommercialController::class, 'emailCustomInvoice'])->name('admin.commercial.custom-invoice.email');
        Route::post('/commercial/facture-personnalisee/{invoice}/whatsapp', [CommercialController::class, 'sendCustomInvoiceWhatsApp'])->name('admin.commercial.custom-invoice.whatsapp');
        Route::get('/commercial/facture-personnalisee/{invoice}/print', [CommercialController::class, 'printCustomInvoice'])->name('admin.commercial.custom-invoice.print');
        Route::post('/commercial/facture-personnalisee/{invoice}/payment', [CommercialController::class, 'recordCustomInvoicePayment'])->name('admin.commercial.custom-invoice.payment');
        Route::get('/commercial/devis/create', [CommercialController::class, 'createQuote'])->name('admin.commercial.quotes.create');
        Route::get('/commercial/devis/{quote}/edit', [CommercialController::class, 'editQuote'])->name('admin.commercial.quotes.edit');
        Route::put('/commercial/devis/{quote}', [CommercialController::class, 'updateQuote'])->name('admin.commercial.quotes.update');
        Route::delete('/commercial/devis/{quote}', [CommercialController::class, 'destroyQuote'])->name('admin.commercial.quotes.destroy');
        Route::post('/commercial/devis/{quote}/duplicate', [CommercialController::class, 'duplicateQuote'])->name('admin.commercial.quotes.duplicate');
        Route::get('/commercial/devis/{quote}/print', [CommercialController::class, 'printQuote'])->name('admin.commercial.quotes.print');
        Route::get('/commercial/commandes/{order}/print', [CommercialController::class, 'printOrder'])->name('admin.commercial.orders.print');
        Route::post('/commercial/devis/{quote}/email', [CommercialController::class, 'emailQuote'])->name('admin.commercial.quotes.email');
        Route::post('/commercial/devis/{quote}/validate', [CommercialController::class, 'validateQuote'])->name('admin.commercial.quotes.validate');
        Route::post('/commercial/livraisons/{delivery}/validate', [CommercialController::class, 'validateDelivery'])->name('admin.commercial.deliveries.validate');
        Route::post('/commercial/factures/{invoice}/paiement', [CommercialController::class, 'recordInvoicePayment'])->name('admin.commercial.invoices.payment');
        Route::get('/commercial/factures/{invoice}/print', [CommercialController::class, 'printInvoice'])->name('admin.commercial.invoices.print');
        Route::get('/commercial/factures/{invoice}/print-fne', [CommercialController::class, 'printFneInvoice'])->name('admin.commercial.invoices.printFne');
        Route::post('/commercial/factures/{invoice}/email', [CommercialController::class, 'emailInvoice'])->name('admin.commercial.invoices.email');
        Route::post('/commercial/factures/{invoice}/whatsapp', [CommercialController::class, 'sendInvoiceWhatsApp'])->name('admin.commercial.invoices.whatsapp');
        Route::post('/commercial/factures/{invoice}/cancel', [CommercialController::class, 'cancelInvoice'])->name('admin.commercial.invoices.cancel');
        Route::post('/commercial/factures/{invoice}/fne', [CommercialController::class, 'certifyInvoice'])->name('admin.commercial.invoices.fne');
        Route::post('/commercial/services', [CommercialController::class, 'storeService'])->name('admin.commercial.services.store');
        Route::put('/commercial/services/{service}', [CommercialController::class, 'updateService'])->name('admin.commercial.services.update');
        Route::delete('/commercial/services/{service}', [CommercialController::class, 'destroyService'])->name('admin.commercial.services.destroy');
        Route::post('/commercial/clients', [CommercialController::class, 'storeClient'])->name('admin.commercial.clients.store');
        Route::put('/commercial/clients/{client}', [CommercialController::class, 'updateClient'])->name('admin.commercial.clients.update');
        Route::delete('/commercial/clients/{client}', [CommercialController::class, 'destroyClient'])->name('admin.commercial.clients.destroy');
        Route::post('/commercial/fournisseurs', [CommercialController::class, 'storeSupplier'])->name('admin.commercial.suppliers.store');
        Route::put('/commercial/fournisseurs/{supplier}', [CommercialController::class, 'updateSupplier'])->name('admin.commercial.suppliers.update');
        Route::delete('/commercial/fournisseurs/{supplier}', [CommercialController::class, 'destroySupplier'])->name('admin.commercial.suppliers.destroy');
        Route::post('/commercial/objectifs', [CommercialController::class, 'storeObjective'])->name('admin.commercial.objectives.store');
        Route::put('/commercial/objectifs/{objective}', [CommercialController::class, 'updateObjective'])->name('admin.commercial.objectives.update');
        Route::delete('/commercial/objectifs/{objective}', [CommercialController::class, 'destroyObjective'])->name('admin.commercial.objectives.destroy');
        Route::post('/commercial/objectifs/{objective}/attributions', [CommercialController::class, 'storeObjectiveAssignment'])->name('admin.commercial.objectives.assignments.store');
        Route::put('/commercial/objectif-attributions/{assignment}', [CommercialController::class, 'updateObjectiveAssignment'])->name('admin.commercial.objectives.assignments.update');
        Route::delete('/commercial/objectif-attributions/{assignment}', [CommercialController::class, 'destroyObjectiveAssignment'])->name('admin.commercial.objectives.assignments.destroy');
        Route::post('/commercial/entrees-stock', [CommercialController::class, 'storeStockEntry'])->name('admin.commercial.stock-entries.store');
        Route::get('/commercial/entrees-stock/nouveau', [CommercialController::class, 'createStockEntry'])->name('admin.commercial.stock-entries.create');
        Route::get('/commercial/sorties-stock/nouveau', [CommercialController::class, 'createStockExit'])->name('admin.commercial.stock-exits.create');
        Route::post('/commercial/sorties-stock', [CommercialController::class, 'storeStockExit'])->name('admin.commercial.stock-exits.store');
        Route::post('/commercial/inventaire', [CommercialController::class, 'storeInventory'])->name('admin.commercial.inventory.store');
        Route::post('/commercial/point-de-vente', [CommercialController::class, 'storePosSale'])->name('admin.commercial.pos.store');
        Route::get('/commercial/point-de-vente/nouveau', [CommercialController::class, 'createPosSale'])->name('admin.commercial.pos.create');
        Route::get('/commercial/point-de-vente/{sale}/edit', [CommercialController::class, 'editPosSale'])->name('admin.commercial.pos.edit');
        Route::put('/commercial/point-de-vente/{sale}', [CommercialController::class, 'updatePosSale'])->name('admin.commercial.pos.update');
        Route::get('/commercial/point-de-vente/{sale}', [CommercialController::class, 'showPosSale'])->name('admin.commercial.pos.show');
        Route::post('/commercial/point-de-vente/{sale}/cancel', [CommercialController::class, 'cancelPosSale'])->name('admin.commercial.pos.cancel');
        Route::delete('/commercial/point-de-vente/{sale}', [CommercialController::class, 'destroyPosSale'])->name('admin.commercial.pos.destroy');
        Route::get('/commercial/{module}', [CommercialController::class, 'module'])->name('admin.commercial.module');
        Route::post('/commercial/proforma', [CommercialController::class, 'storeProforma'])->name('admin.commercial.proforma.store');
        Route::get('/admin/commercial', [CommercialController::class, 'index']);
        Route::middleware('super_admin')->group(function () {
            Route::get('/demorequests', [DemoRequestController::class, 'index'])->name('admin.demorequests');
            Route::get('/admin/demorequests', [DemoRequestController::class, 'index']);
        });
        Route::get('/setting', [SettingController::class, 'index'])->name('admin.settings');
        Route::middleware('super_admin')->group(function () {
            Route::get('/setting/modules', [SettingController::class, 'moduleSettingsIndex'])->name('admin.settings.modules');
            Route::patch('/setting/modules', [SettingController::class, 'updateModules'])->name('admin.settings.modules.update');
        Route::patch('/setting/whatsapp', [SettingController::class, 'updateWhatsapp'])->name('admin.settings.whatsapp.update');
        });
        Route::get('/setting/{module}', [SettingController::class, 'module'])->name('admin.settings.module');
        Route::resource('departments', DepartmentController::class, ['as' => 'admin'])->only(['index', 'store', 'update', 'destroy']);
        Route::resource('designations', DesignationController::class, ['as' => 'admin'])->only(['index', 'store', 'update', 'destroy']);
        Route::patch('/setting/general', [SettingController::class, 'updateGeneral'])->name('admin.settings.general.update');
        Route::patch('/setting/email', [SettingController::class, 'updateEmail'])->name('admin.settings.email.update');
        Route::patch('/setting/theme', [SettingController::class, 'updateTheme'])->name('admin.settings.theme.update');
        Route::patch('/setting/cinetpay', [SettingController::class, 'updateCinetPay'])->name('admin.settings.cinetpay.update');
        Route::get('/comptabilite/bilans', [AnnualReportController::class, 'index'])->name('admin.bilans.index');
        Route::get('/comptabilite/bilans/{report}', [AnnualReportController::class, 'show'])->name('admin.bilans.show');
        Route::get('/comptabilite/bilans/{report}/pdf', [AnnualReportController::class, 'download'])->name('admin.bilans.download');
        Route::post('/comptabilite/bilans/{report}/vu', [AnnualReportController::class, 'acknowledge'])->name('admin.bilans.acknowledge');
        Route::get('/comptabilite/journal', [ComptabiliteController::class, 'journalComptable'])->name('admin.comptabilite.journal');
        Route::get('/comptabilite/declaration-tva', [ComptabiliteController::class, 'declarationTva'])->name('admin.comptabilite.declaration_tva');
        Route::patch('/comptabilite/factures-fournisseurs/{supplierInvoice}/regime', [ComptabiliteController::class, 'updateSupplierInvoiceRegime'])->name('admin.comptabilite.supplierInvoices.regime');
        Route::patch('/setting/fiscalite/fait-generateur', [SettingController::class, 'updateTaxBasis'])->name('admin.settings.tax-basis.update');
        Route::post('/setting/fiscalite/taux', [SettingController::class, 'storeTaxRate'])->name('admin.settings.tax-rates.store');
        Route::patch('/setting/fiscalite/taux/{taxRate}', [SettingController::class, 'updateTaxRate'])->name('admin.settings.tax-rates.update');
        Route::delete('/setting/fiscalite/taux/{taxRate}', [SettingController::class, 'destroyTaxRate'])->name('admin.settings.tax-rates.destroy');
        Route::post('/setting/email/test', [SettingController::class, 'testEmail'])->name('admin.settings.email.test');
        Route::post('/setting/general/renew', [SettingController::class, 'renewSubscription'])->name('admin.settings.general.renew');
        Route::get('/admin/setting', [SettingController::class, 'index']);
        Route::resource('succursales', SuccursaleController::class, ['as' => 'admin'])->only(['index', 'store', 'update', 'destroy'])->names([
            'index' => 'admin.succursales.index',
            'store' => 'admin.succursales.store',
            'update' => 'admin.succursales.update',
            'destroy' => 'admin.succursales.destroy',
        ]);
        Route::patch('/succursales/{succursale}/toggle', [SuccursaleController::class, 'toggle'])->name('admin.succursales.toggle');
        Route::patch('/profile', [UserController::class, 'updateProfile'])->name('admin.profile.update');
        Route::patch('/profile/password', [UserController::class, 'updatePassword'])->name('admin.profile.password.update');
        Route::get('/profile', [UserController::class, 'profile'])->name('admin.profile');

        Route::resource('users', UserController::class, ['as' => 'admin'])->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])->names([
            'index' => 'admin.users.index',
            'create' => 'admin.users.create',
            'store' => 'admin.users.store',
            'edit' => 'admin.users.edit',
            'update' => 'admin.users.update',
            'destroy' => 'admin.users.destroy',
        ]);
        Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('admin.users.toggleStatus');
        Route::patch('/users/{user}/permissions', [UserController::class, 'updatePermissions'])->name('admin.users.permissions.update');

        Route::middleware('super_admin')->group(function () {
            Route::patch('/entreprises/{entreprise}/toggle', [EntrepriseController::class, 'toggle'])->name('admin.entreprises.toggle');
            // Pas de route « show » : la fiche se consulte et se modifie dans le même écran.
            Route::resource('entreprises', EntrepriseController::class, ['as' => 'admin'])
                ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
                ->names([
                'index' => 'admin.entreprises.index',
                'create' => 'admin.entreprises.create',
                'store' => 'admin.entreprises.store',
                'edit' => 'admin.entreprises.edit',
                'update' => 'admin.entreprises.update',
                'destroy' => 'admin.entreprises.destroy',
            ]);
        });
    });
});
