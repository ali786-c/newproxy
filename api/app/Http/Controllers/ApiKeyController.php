<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            ApiKey::where('user_id', $request->user()->id)->latest()->get()
        );
    }

    public function store(Request $request)
    {
        // Support both 'name' (frontend) and 'key_name' (backend)
        if ($request->has('name') && !$request->has('key_name')) {
            $request->merge(['key_name' => $request->name]);
        }

        $request->validate([
            'key_name' => 'required|string|max:255',
            'abilities' => 'nullable|array'
        ]);

        $plainTextKey = 'uproxy_' . Str::random(40);

        $apiKey = ApiKey::create([
            'user_id'   => $request->user()->id,
            'key_name'  => $request->key_name,
            'key_hash'  => ApiKey::hash($plainTextKey),
            'abilities' => $request->abilities ?? ['*'],
            'is_active' => true,
        ]);

        // Add 'name' to the response for frontend compatibility
        $apiKey->name = $apiKey->key_name;
        $apiKey->plain_text_key = $plainTextKey;

        // Audit Log for API Key creation
        \App\Models\AdminLog::log(
            'api_key_created',
            "User #{$request->user()->id} ({$request->user()->email}) created a new API key: '{$request->key_name}'.",
            $request->user()->id
        );

        return response()->json($apiKey, 201);
    }

    public function destroy($id, Request $request)
    {
        $key = ApiKey::where('user_id', $request->user()->id)->findOrFail($id);
        $keyName = $key->key_name;
        $key->delete();

        // Audit Log for API Key revocation
        \App\Models\AdminLog::log(
            'api_key_revoked',
            "User #{$request->user()->id} ({$request->user()->email}) revoked API key: '{$keyName}'.",
            $request->user()->id
        );

        return response()->json(['message' => 'API Key deleted']);
    }
}
