<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PbbObject;
use Illuminate\Http\Request;

class PbbController extends Controller {
    public function checkNop(Request $request) {
        $pbb = PbbObject::where('nop', $request->nop)->first();
        return response()->json(['data' => $pbb]);
    }
}