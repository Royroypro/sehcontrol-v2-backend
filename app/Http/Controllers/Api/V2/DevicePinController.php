<?php
//DevicePinController.php
namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Device;
use App\Support\RustDeskPin;

class DevicePinController extends Controller
{
    private const DEFAULT_UUID = 'd30dcdb0-1ce7-4614-8c26-c451790fb2be';
    private const DEFAULT_PIN  = '123456';

    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'device_uid'   => ['required','string','max:128'],
            'pin_plain'    => ['nullable','string','min:4','max:32'],
            'uuid_machine' => ['nullable','string','max:64'], // futuro: lo manda agente
        ]);

        $device = Device::where('device_uid', $data['device_uid'])->first();
        if (!$device) {
            return response()->json([
                'message' => 'Device not registered',
                'reason'  => 'not_registered',
            ], 404);
        }

        // Si el frontend manda uuid_machine, lo guardamos (si no, usamos el que ya esté en DB)
        if (!empty($data['uuid_machine'])) {
            $device->uuid_machine = $data['uuid_machine'];
        }

        $uuid = $device->uuid_machine ?: self::DEFAULT_UUID;
        $pin  = $data['pin_plain'] ?? self::DEFAULT_PIN;

        $device->unlock_pin = RustDeskPin::encrypt($pin, $uuid);
        $device->save();

        return response()->json([
            'message'      => 'PIN configured',
            'device_uid'   => $device->device_uid,
            'uuid_machine' => $device->uuid_machine,
            'unlock_pin'   => $device->unlock_pin,
        ]);
    }
}
