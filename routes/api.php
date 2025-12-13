<?php
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\ParkingController;
use App\Http\Controllers\Api\PublicController;  
use App\Http\Controllers\QuickMessageController;


Route::post('/register',[AuthController::class,'register']);
Route::post('/login',[AuthController::class,'login']);

Route::middleware('auth:sanctum')->group(function () {

    // Araç işlemleri
    Route::post('/vehicle/activate',[VehicleController::class,'activate']);
    Route::get('/vehicle/my',[VehicleController::class,'myVehicles']);
    Route::get('/vehicle/{id}',[VehicleController::class,'info']);

    // Park işlemleri
    Route::post('/parking/set',[ParkingController::class,'setParking']);
    Route::get('/parking/latest/{id}',[ParkingController::class,'latest']);
    Route::delete('/parking/delete/{id}',[ParkingController::class,'deleteParking']);

    Route::post('/user/push-id', [AuthController::class, 'savePushId']);


});

Route::get('/v/{tag}', [PublicController::class, 'viewVehicle']);
Route::post('/v/{tag}/message', [PublicController::class, 'sendMessage']);

Route::middleware('auth:sanctum')->post('/user/push-id', function (Request $req) {
    $req->validate(['push_id' => 'required']);
    $user = $req->user();
    $user->push_id = $req->push_id;
    $user->save();
    return ['status' => 'ok'];
});

Route::get('/public/quick-messages', [QuickMessageController::class, 'index']);
Route::post('/public/quick-message/send', [QuickMessageController::class, 'send']);
Route::get('/public/vehicle/{vehicle_uuid}', [PublicController::class, 'vehicleProfile']);


