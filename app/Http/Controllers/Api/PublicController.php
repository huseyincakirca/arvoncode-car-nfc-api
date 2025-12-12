<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vehicle;
use App\Models\Parking;
use App\Models\User;

class PublicController extends Controller
{
    // Kart okutulunca araç bilgisi döndür
    public function viewVehicle($tag)
    {
        $tag = trim($tag);
        // Global scope'ları kapat -> user_id filtresi devre dışı
        $vehicle = Vehicle::withoutGlobalScopes()
            ->where('vehicle_id', $tag)
            ->first();

        if (!$vehicle) {
            return response()->json(['error' => 'Araç bulunamadı'], 404);
        }

        return response()->json([
            'vehicle' => $vehicle,
            'owner'   => $vehicle->user,
        ]);
    }

        // Kart üzerinden araç sahibine mesaj gönder
        public function sendMessage(Request $req, $tag)
        {
            // 1) Parametreyi temizle (newline, boşluk, tab hepsini sil)
            $cleanTag = trim($tag);

            // 2) Validasyon
            $req->validate([
                'message' => 'required|string|max:500',
                'location' => 'nullable|string|max:255',
            ]);

            // 3) global scope'ları kapat ve aracı bul
            $vehicle = Vehicle::withoutGlobalScopes()
                ->where('vehicle_id', $cleanTag)
                ->first();

            if (!$vehicle) {
                return response()->json(['error' => 'Araç bulunamadı'], 404);
            }

            // 4) Araç sahibini bul
            $owner = $vehicle->user;
            if (!$owner) {
                return response()->json(['error' => 'Sahip bulunamadı'], 404);
            }

            // 5) Mesajı Parking tablosuna kaydet
            $record = Parking::create([
                'user_id'    => $owner->id,
                'vehicle_id' => $vehicle->id,
                'message'    => $req->message,
                'location'   => $req->location ?? null,
            ]);

            // 6) Başarılı cevap
            return response()->json([
                'status'     => 'success',
                'message'    => 'Mesaj kaydedildi',
                'record_id'  => $record->id,
                'vehicle_id' => $vehicle->vehicle_id,
            ], 201);
        }

}
