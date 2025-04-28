<?php

namespace MicroweberPackages\Modules\LicenseServer\Http\Controllers\Api;

use Illuminate\Http\Request;
use MicroweberPackages\Modules\LicenseServer\Http\Controllers\ApiBaseController;
use MicroweberPackages\Modules\LicenseServer\Models\IpAddress;
use MicroweberPackages\Modules\LicenseServer\Models\License;

class LegacyLicenseController extends ApiBaseController
{


    public function validateLocal(Request $request)
    {


        $localKey = $request->get('local_key', false);
        $relType = $request->get('rel_type', false);

        if (!$localKey) {
            return $this->respondWithError('Invalid local key');
        }

        if (!$relType) {
            return $this->respondWithError('Invalid rel type');
        }

        $findLicense = License::where('license_key', $localKey)->first();
        if (!$findLicense) {
            return $this->respondWithError('Invalid license key');
        }
        $data = [];

        $licenseStatus = 'inactive';
        $active = false;
        if ($findLicense->status == 'active' || $findLicense->status == 'Active') {
            $active = true;
            $licenseStatus = 'active';
        }
        if ($findLicense->is_lifetime) {
            $active = true;
            $licenseStatus = 'active';
        }


        $data[$relType] = [
            'rel_type' => $relType,
            'active' => $licenseStatus == 'active',
            'status' =>$licenseStatus,
            'local_key_hash' => md5($findLicense->license_key),

        ];
        return response()->json($data);
    }



    public function validateLegacy(Request $request)
    {
        $localKey = $request->get('local_key', false);
        $relType = $request->get('rel_type', false);

        if (!$localKey) {
            return $this->respondWithError('Invalid local key');
        }

        if (!$relType) {
            return $this->respondWithError('Invalid rel type');
        }
        // Dobule check form WHMCS
        $checkWhmcs = $this->checkWhmcs($localKey, $relType);
        if ($checkWhmcs) {
            return $checkWhmcs;
        }

        $findLicense = License::where('license_key', $localKey)->first();
        if (!$findLicense) {
            return $this->respondWithError('Invalid license key');
        }

//        $ipAddress = IpAddress::where('license_id', $findLicense->id)->first();
//        $serverIpAddress = user_ip();

//        if (!$ipAddress) {
//            $ipAddress = IpAddress::create([
//                'license_id' => $findLicense->id,
//                'ip_address' => $serverIpAddress,
//            ]);
//        }

//        if ($ipAddress && $ipAddress->ip_address == $serverIpAddress) {

            $email = '';
            $registeredName = '';
            $relName = '';

            $licensed = $findLicense->licensed()->first();
            if ($licensed) {
                $relName = content_title($licensed->licensable_id);
            }

            if ($findLicense->user_id > 0) {
                $email = user_email($findLicense->user_id);
                $registeredName = user_name($findLicense->user_id);
            }
            $licenseStatus = 'Inactive';
            $active = false;
            if ($findLicense->status == 'active' || $findLicense->status == 'Active') {
                $active = true;
                $licenseStatus = 'Active';
            }
            if ($findLicense->is_lifetime) {
                $active = true;
                $licenseStatus = 'Active';
            }
            return response()->json([
                $relType => [
                    'rel_type' => $relType,
                    'active'=> $active,
                    'status' => $licenseStatus,
                    'local_key_hash' => md5($findLicense->license_key),
                    'registered_name' => $registeredName,
                    'registeredname' => $registeredName,
                    'email' => $email,
                    'rel_name' => $relName,
                    'reg_on' => $findLicense->created_at,
                    'due_on' => $findLicense->expiration_date,
                    'billing_cycle' => 'Monthly',
                    'productid' => 0,
                    'serviceid' => 0,
                ]
            ]);
//        }

//        return $this->respondWithError('The IP address '.$serverIpAddress.' is not allowed. Please contact the license provider.');

    }

    public function checkWhmcs($localKey, $relType) {

        $whmcsUrl = 'https://update.microweberapi.com/?api_function=validate_licenses&local_key='.$localKey.'&rel_type='.$relType;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $whmcsUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $output = curl_exec($ch);

        curl_close($ch);

        if ($output) {
            $output = json_decode($output, true);
            if (isset($output[$relType])) {
                if (isset($output[$relType]['status']) && (
                        $output[$relType]['status'] == 'Active' ||
                        $output[$relType]['status'] == 'active'
                    )) {
                    $output[$relType]['active'] = true;
                    return response()->json($output);
                }

            }
        }

        return false;

    }

    public function respondWithError($message = false)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ]);
    }

}
