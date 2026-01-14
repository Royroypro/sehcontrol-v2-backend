<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Device;

class AdminDeviceRegisterController extends Controller
{
    public function __invoke(Request $request)
    {
        // Admin ya fue validado por el middleware: admin.key (VerifyAdminKey)

        $v = Validator::make($request->all(), [
            'device_uid' => ['required','string','min:16','max:128'],
            'alias'      => ['nullable','string','max:120'],
            'platform'   => ['nullable','string','max:32'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => $v->errors(),
            ], 422);
        }

        $deviceUid = trim($request->input('device_uid'));
        $alias     = $request->input('alias');
        $platform  = $request->input('platform');

        $device = Device::firstOrCreate(
            ['device_uid' => $deviceUid],
            [
                'alias'    => $alias,
                'platform' => $platform,
            ]
        );

        // Si ya existía, actualiza datos opcionales
        if (!$device->wasRecentlyCreated) {
            $dirty = false;
            if ($alias && $device->alias !== $alias) { $device->alias = $alias; $dirty = true; }
            if ($platform && $device->platform !== $platform) { $device->platform = $platform; $dirty = true; }
            if ($dirty) $device->save();
        }

        return response()->json([
            'message' => $device->wasRecentlyCreated ? 'Device registered' : 'Device already registered',
            'device_uid' => $device->device_uid,
            'id' => $device->id,
        ], 200);
    }
}
