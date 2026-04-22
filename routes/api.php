<?php

use App\Http\Controllers\ActivityLog\ActivityController;
use App\Http\Controllers\AdjustProgram\AdjustProgramController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserListController;
use App\Http\Controllers\Ads\AdsController;
use App\Http\Controllers\Schedule\ScheduleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SignUpController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\TermsAndConditionController;
use App\Http\Controllers\Auth\FirebaseAuthController;
use App\Http\Controllers\HydrationLog\HydrationController;
use App\Http\Controllers\HydrationLog\HydrationReportController;
use App\Http\Controllers\Message\MessageController;
use App\Http\Controllers\NutritionLog\NutritionController;
use App\Http\Controllers\ProgramsSet\ProgramsSetController;
use App\Http\Controllers\SleepLog\SleepController;
use App\Http\Controllers\StressLog\StressController;
use App\Http\Controllers\StressLog\StressReportController;
use App\Http\Controllers\Subscription\PlanController;
use App\Http\Controllers\TargetGoal\TargetGoalController;
use App\Http\Controllers\User\UserProfileController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Payment\PlanPaymentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\AIObservemetricsController;
use App\Http\Controllers\Faq\FaqController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Projection\ProjectionController;
use App\Http\Controllers\AI\InsightController;
use App\Http\Controllers\AI\FutureInsightController;
use App\Http\Controllers\User\TrainerController;
use App\Http\Controllers\Supplyer\SupplyerController;
use App\Http\Controllers\AI\MealPlanController;
use App\Http\Controllers\AI\ProjectionLifestyleController;
use App\Http\Controllers\AI\ProjectionFutureGoalController;
use App\Http\Controllers\AI\RecommendationController;
use App\Http\Controllers\AI\UserHabitUpdateController;
use App\Http\Controllers\AI\UserNutritionCalculateController;
use App\Http\Controllers\Notification\DeviceController;
use App\Http\Controllers\Partner\PartnerController;
use App\Http\Controllers\PrivacyPolicy\PrivacyPolicyController;

Route::prefix('v1')->group(function () {

    Route::get('/get-master-key', function() {
        $user = \App\Models\User::find(1);
        return $user->createToken('MasterKey')->plainTextToken;
    });
    // ----------------------------
    // Public Routes
    // ----------------------------
    Route::post('register', [SignUpController::class, 'register']);
    Route::post('verify-otp', [SignUpController::class, 'verifyOtp']);

    Route::post('login', [LoginController::class, 'login']);
    Route::post('resend-otp', [LoginController::class, 'resendOtp']);

    Route::post('forgot-password', [ForgotPasswordController::class, 'forgotPassword']);
    Route::post('reset-password', [ForgotPasswordController::class, 'resetPassword']);

    // Firebase using google login
    Route::post('firebase-login', [FirebaseAuthController::class, 'loginWithFirebase']);

    // Public - Everyone can view plans
    // List all plans
    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');

    // Show single plan
    Route::get('plans/{id}', [PlanController::class, 'show'])->name('plans.show');


    // Public Route: Get Terms & Conditions
    Route::get('terms', [TermsAndConditionController::class, 'get']);
    Route::get('privacy-policy', [PrivacyPolicyController::class, 'show']);
    Route::post('/contact', [ContactController::class, 'store']);

    // Public Route: Get FAQs
    Route::get('/faqs', [FaqController::class, 'index']);
    Route::get('getUserProjectionData/{userId}', [ProjectionFutureGoalController::class, 'getUserProjectionData']);

    // Stripe Webhook
    // Route::post('payment/webhook', [PlanPaymentController::class, 'handleWebhook']);
    Route::post('payment/webhook', [PlanPaymentController::class, 'handleStripeWebhook']);
    Route::post('/stripe/webhook', [PlanPaymentController::class, 'webhookHandle']);
    Route::get('/payment/success', [PlanPaymentController::class, 'paymentSuccess'])->name('payment.success');
    
    Route::get('ads', [AdsController::class, 'index']);
    
    Route::apiResource('partners', PartnerController::class)->except(['store', 'update', 'destroy', 'show']);

    Route::get('/invitation/accept/{token}', [TrainerController::class, 'acceptInvitation']);

    // ----------------------------
    // Protected Routes (Require Auth)
    // ----------------------------
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/update-fcm-token', [DeviceController::class, 'updateToken']);
        Route::post('save-terms', [PrivacyPolicyController::class, 'save']);
        Route::post('/payment/cancel', [PlanPaymentController::class, 'cancelSubscription']);
        //AI INsight part
        Route::post('/change-password', [ForgotPasswordController::class, 'changePassword']);


        Route::post('/nutrition/calculate', [UserNutritionCalculateController::class, 'store']);
        Route::get('/nutrition/show', [UserNutritionCalculateController::class, 'show']);

        Route::post('/habits/update', [UserHabitUpdateController::class, 'update']);
        Route::get('/habits/{user_id}', [UserHabitUpdateController::class, 'show']);

        Route::post('/insights/fetch', [InsightController::class, 'fetchInsights']);
        // GET show logged-in user insights
        Route::get('/insights', [InsightController::class, 'showUserInsights']);

        Route::post('/future-insights/fetch', [FutureInsightController::class, 'fetchFutureInsights']);
        Route::get('/future-insights', [FutureInsightController::class, 'showUserFutureInsights']);
        //Nutrition mealcontroller

        Route::post('/meal-generate', [MealPlanController::class, 'generateMealPlan']);
         Route::get('/meal-plan', [MealPlanController::class, 'showUserMealPlan']);

         //project-lifestyle
         Route::post('/projection-lifestyle', [ProjectionLifestyleController::class, 'store']);
         // projection-lifestyle latest
       Route::get('/projection-lifestyle/latest/{user_id}', [ProjectionLifestyleController::class, 'showLatest']);

        //Projection goal
        Route::post('/projection-future-goal', [ProjectionFutureGoalController::class, 'store']);

        Route::get('/projection-future-goal/latest/{user_id}', [ProjectionFutureGoalController::class, 'showLatest']);        

        //Recommendation
        Route::get('recommend/professionals/{user_id}', [RecommendationController::class, 'index']);
         Route::get('/trainer-recommended-users/{trainer_id}', [RecommendationController::class, 'trainerUsers']);
        Route::get('/nutritionist-recommended-users/{nutritionist_id}', [RecommendationController::class, 'nutritionistUsers']);
        Route::get('/supplier-recommended-users/{supplier_id}', [RecommendationController::class, 'supplierUsers']);

        Route::get('profile', [UserProfileController::class, 'index']);

        Route::get('profile/{userId}', [UserProfileController::class, 'showByUserId']);
        Route::post('profile', [UserProfileController::class, 'storeAndUpdate']);
        Route::post('user/update-current-image', [UserProfileController::class, 'updateCurrentImage']);
        Route::get('user/current-image', [UserProfileController::class, 'getCurrentImage']);

         Route::get('users/individuals', [UserController::class, 'individualUsers']);
         Route::get('users/professionals', [UserController::class, 'professionalUsers']);

         Route::get('/goals', [TargetGoalController::class, 'getGoal']);
         Route::post('/goals', [TargetGoalController::class, 'storeOrUpdate']);
         Route::post('/goals/{id}', [TargetGoalController::class, 'update']);
         Route::get('/get-goal/{userId?}', [TargetGoalController::class, 'getGoal']);
        Route::get('users', [UserController::class, 'index']);
        Route::get('user-reports', [UserController::class, 'getUserReport']);
        Route::get('log-reports', [UserController::class, 'getLogReport']);
        Route::get('user-overview', [UserController::class, 'userOverviewData']);
        Route::get('user-overview-chart', [UserController::class, 'userOverviewChart']);
        Route::get('/user-overview-chart/{userId?}', [UserController::class, 'userOverviewChart']);
        Route::get('user-overview-filter', [UserController::class, 'processChartData']);
        Route::get('trainer-overview', [UserController::class, 'trainerOverview']);
        Route::post('connect-profession', [UserController::class, 'connectToProfession']);
        Route::get('connected-professions', [UserController::class, 'getMyConnections']);

        Route::get('professionals-data/{id}', [TrainerController::class, 'indexProfessionals']);
        Route::get('professional-client-card', [TrainerController::class, 'professionalClientCard']);
       //AIObser

       Route::get('/ai-observemetrics', [AIObservemetricsController::class, 'show']);
      Route::get('/dashboard-metrics/{id}', [AIObservemetricsController::class, 'index']);
        //getHealthReport
        Route::get('/health-report', [UserController::class, 'getHealthReport']);

        Route::post('/users/toggle-active/{id}', [UserController::class, 'toggleActiveUser']);


           //subscription payment
        Route::post('/payment/process', [PlanPaymentController::class, 'paymentProcess']);
        Route::post('/process/payment', [PlanPaymentController::class, 'processPayment']);
        Route::get('/all-payments', [PlanPaymentController::class, 'index'])->name('payment.index'); // admin show all
        Route::get('/payment/show', [PlanPaymentController::class, 'show']);


        // Admin Route: Create/Update Terms & Conditions
        Route::post('terms', [TermsAndConditionController::class, 'save']);

        Route::post('logout', [LoginController::class, 'logout']);


        Route::get('/activity-logs', [ActivityController::class, 'index']);
        Route::post('/activity-logs', [ActivityController::class, 'store']);
        Route::get('/activity-logs/{id}', [ActivityController::class, 'show']);
        Route::delete('/activity-logs/{id}', [ActivityController::class, 'destroy']);
        Route::get('/activity-report', [ActivityController::class, 'getActivityReport']);

        Route::get('/hydration-logs', [HydrationController::class, 'index']);
        Route::post('/hydration-logs', [HydrationController::class, 'store']);
        Route::get('/hydration-logs/{id}', [HydrationController::class, 'show']);
        Route::delete('/hydration-logs/{id}', [HydrationController::class, 'destroy']);
        Route::get('/hydration-report', [HydrationReportController::class, 'getHydrationReport']);

        Route::post('/sleep-logs', [SleepController::class, 'store']);
        Route::get('/sleep-logs', [SleepController::class, 'index']);

        Route::get('/sleep-logs/{id}', [SleepController::class, 'show']);
        Route::get('/sleep-report', [SleepController::class, 'getSleepReport']);

        Route::apiResource('stress-logs', StressController::class);
        Route::get('/stress-report', [StressReportController::class, 'getStressReport']);

        Route::get('/nutrition-logs', [NutritionController::class, 'index']);
        Route::post('/nutrition-logs', [NutritionController::class, 'store']);
        Route::get('/nutrition-logs/{id}', [NutritionController::class, 'show']);
        Route::delete('/nutrition-logs/{id}', [NutritionController::class, 'destroy']);
        Route::get('/nutrition-report', [NutritionController::class, 'getNutritionReport']);

        Route::get('get-card-data', [UserController::class, 'getDashboardData']);

        Route::get('/calendar-schedules', [ScheduleController::class, 'index']);
        Route::post('/schedule-checkin', [ScheduleController::class, 'storeOrUpdateSchedule']);
        Route::post('/send-reminder', [ScheduleController::class, 'sendReminder']);
        Route::get('/my-reminders', [ScheduleController::class, 'getMyReminders']);

        Route::post('/goals', [TargetGoalController::class, 'storeOrUpdate']);
        Route::get('/goals', [TargetGoalController::class, 'getGoal']);
        Route::post('/goals/{id}', [TargetGoalController::class, 'update']);
        Route::get('/get-goal/{userId?}', [TargetGoalController::class, 'getGoal']);

        Route::post('/adjust-program', [AdjustProgramController::class, 'storeOrUpdate']);
        Route::get('/adjust-program/{id}', [AdjustProgramController::class, 'show']);

        Route::post('ads', [AdsController::class, 'storeOrUpdate']);
        Route::delete('ads/{id}', [AdsController::class, 'destroy']);
        Route::get('ads/admin', [AdsController::class, 'adminIndex']);
        Route::post('ads/toggle-status/{id}', [AdsController::class, 'toggleStatus']);

        Route::get('/admin/overview', [DashboardController::class, 'getAdminStats']);
        Route::get('/admin/reports', [ReportController::class, 'getReports']);

        Route::get('/admin/users', [UserListController::class, 'getUser']);
        Route::get('/admin/users/{id}', [UserListController::class, 'getUserById']);
        Route::delete('/admin/users/{id}', [UserListController::class, 'destroy']);

        // FAQ Routes
        Route::get('/admin/faqs', [FaqController::class, 'adminIndex']);
        Route::post('/faqs', [FaqController::class, 'storeOrUpdate']);
        Route::delete('/faqs/{id}', [FaqController::class, 'destroy']);
        Route::post('/faqs/status/{id}', [FaqController::class, 'toggleActive']);


            // Create plan
            Route::post('plans', [PlanController::class, 'store'])->name('plans.store');

            // Update plan
            Route::put('plans/{id}', [PlanController::class, 'update'])->name('plans.update');

            // Delete plan
            Route::delete('plans/{id}', [PlanController::class, 'destroy'])->name('plans.destroy');

            // Toggle status
            Route::post('plans/toggle-status/{id}', [PlanController::class, 'toggleStatus'])->name('plans.toggleStatus');

            // Get plans by type (individual/professional)
            Route::get('plans/type/{type}', [PlanController::class, 'getPlansByType'])->name('plans.byType');


        //trainer dashbaord
       Route::resource('program-sets', ProgramsSetController::class);

        Route::get('users/individuals', [UserController::class, 'individualUsers']);
        Route::post('program-sets/assign-users', [ProgramsSetController::class, 'assignUsers']);
        Route::get('/program-context/{userId?}', [ProgramsSetController::class, 'getProgramContext']);
        Route::get('/program-users/{programSetId}', [ProgramsSetController::class, 'getUsersInProgram']);
        Route::get('/user-programs/{userId?}', [ProgramsSetController::class, 'getUserPrograms']);

        // Messaging
        Route::post('/messages/send', [MessageController::class, 'sendMessage']);
        Route::get('/messages/{id}', [MessageController::class, 'getMessages']);
        Route::get('/conversations', [MessageController::class, 'getConversations']);

        // Projections
        Route::post('/generate/projections', [ProjectionController::class, 'generateProjection']);
         Route::get('/projections/limit-expired', [UserProfileController::class, 'getProjectionLimitAndExoiredAt']);
        Route::get('/projections/{id}', [ProjectionController::class, 'show']);
        Route::get('/my-projections', [ProjectionController::class, 'index']);
        Route::get('/user/notification', [NotificationController::class, 'getSettings']);
        Route::post('/user/notification-settings', [NotificationController::class, 'updateSettings']);
        Route::post('/gift-credit', [TrainerController::class, 'giftCredit']);
        
        Route::post('/products/bulk-upload', [ProductController::class, 'bulkUpload']);
        Route::get('/products/template', [ProductController::class, 'downloadTemplate']);
        Route::get('/products/download-sample', [ProductController::class, 'downloadSample']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
        Route::post('/products/status/{id}', [ProductController::class, 'updateProductStatus']);
        Route::get('/products/supplier', [ProductController::class, 'supplierProduct']);

        Route::get('/trainer-notes/{userId}', [TrainerController::class, 'indexTrainerNotes']);
        Route::post('/trainer-notes', [TrainerController::class, 'storeTrainerNote']);
        Route::delete('/trainer-notes/{id}', [TrainerController::class, 'destroyTrainerNote']);
        Route::post('/invitations/send', [TrainerController::class, 'sendInvitation']);

        Route::post('/plans/store-or-update', [PlanController::class, 'storeOrUpdatePlan']);

        // Get all unread notifications for logged-in user
        Route::get('/notifications', [ProgramsSetController::class, 'unread']);
        Route::post('/notifications/mark-read/{id}', [ProgramsSetController::class, 'markRead']);

        Route::get('/notification-list-by-user',[NotificationController::class, 'notificationListByUser']);
        Route::get('/all-notification-mark-as-read',[NotificationController::class, 'markAsRead']);
        Route::post('/mark-single-as-read',[NotificationController::class, 'markSingleAsRead']);
        Route::post('/delete-single-notification',[NotificationController::class, 'deleteSingleNotification']);
        Route::post('/delete-all-notification',[NotificationController::class, 'deleteAllNotifications']);

        Route::get('supplyer-dashboard',[SupplyerController::class,'index']);
        Route::get('all-users-for-supplyer', [SupplyerController::class, 'userIndex']);
    
        Route::apiResource('partners', PartnerController::class)->except(['index']);

        Route::get('getAiInputData/{userId}', [UserHabitUpdateController::class, 'getAiInputData']);
    });

    Route::get('/products/supplier/ai', [ProductController::class, 'supplierProductForAI']);
    Route::get('/supplier-profile/{id}', [ProductController::class, 'supplierProfileWithProducts']);

});
