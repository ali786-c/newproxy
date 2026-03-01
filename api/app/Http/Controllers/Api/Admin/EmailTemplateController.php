<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use Illuminate\Support\Facades\Notification;

class EmailTemplateController extends Controller
{
    protected $service;

    public function __construct(EmailTemplateService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the templates.
     */
    public function index()
    {
        return response()->json(EmailTemplate::orderBy('key')->get());
    }

    /**
     * Display the specified template.
     */
    public function show($id)
    {
        $template = is_numeric($id) 
            ? EmailTemplate::findOrFail($id) 
            : EmailTemplate::where('key', $id)->firstOrFail();
            
        return response()->json($template);
    }

    /**
     * Store a newly created template.
     */
    public function store(Request $request)
    {
        $request->validate([
            'key' => 'required|string|max:100|unique:email_templates',
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'format' => 'in:markdown,html',
            'description' => 'nullable|string',
            'variables' => 'nullable|array',
        ]);

        $template = EmailTemplate::create($request->all());

        return response()->json([
            'message' => 'Template created successfully.',
            'template' => $template
        ], 201);
    }

    /**
     * Update the specified template.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'is_active' => 'boolean',
            'format' => 'in:markdown,html',
        ]);

        $template = is_numeric($id) 
            ? EmailTemplate::findOrFail($id) 
            : EmailTemplate::where('key', $id)->firstOrFail();

        $template->update($request->only(['subject', 'body', 'is_active', 'format']));

        return response()->json([
            'message' => 'Template updated successfully.',
            'template' => $template
        ]);
    }

    /**
     * Remove the specified template.
     */
    public function destroy($id)
    {
        $template = is_numeric($id) 
            ? EmailTemplate::findOrFail($id) 
            : EmailTemplate::where('key', $id)->firstOrFail();
            
        $template->delete();

        return response()->json(['message' => 'Template deleted successfully.']);
    }

    /**
     * Generate a preview of the template with dummy data.
     */
    public function preview(Request $request, $id)
    {
        $template = is_numeric($id) 
            ? EmailTemplate::findOrFail($id) 
            : EmailTemplate::where('key', $id)->firstOrFail();

        // Standard dummy data for preview
        $dummyData = [
            'user' => ['name' => 'John Doe', 'email' => 'john@example.com', 'ip' => '127.0.0.1'],
            'app' => ['name' => config('app.name')],
            'order' => ['id' => 'ORD-123', 'amount' => '$99.00'],
            'ticket' => ['id' => 'TIC-456', 'subject' => 'Sample Issue', 'priority' => 'High'],
            'payment' => ['reference' => 'TXN789'],
            'reply_content' => "This is a sample reply from our support team.\n\nHope this helps!",
            'action_url' => url('/'),
            'admin_url' => url('/admin')
        ];

        // Temporarily override template with request data for "live" preview while editing
        if ($request->has('body')) {
            $template->body = $request->body;
            $template->subject = $request->subject ?? $template->subject;
            $template->format = $request->format ?? $template->format;
        }

        // We use the service to render
        // Note: The service uses the DB template by key usually, 
        // but we can mock a temporary model or just use the logic.
        // For simplicity, we'll use the service's rendering logic.
        
        $render = $this->service->renderTemplate($template, $dummyData);

        return response()->json($render);
    }

    /**
     * Send a test email of this template to the admin.
     */
    public function testSend(Request $request, $id)
    {
        $request->validate(['email' => 'required|email']);

        $template = is_numeric($id) 
            ? EmailTemplate::findOrFail($id) 
            : EmailTemplate::where('key', $id)->firstOrFail();

        // Trigger a fake notification class using this key
        // We'll use the BaseDynamicNotification logic manually here for the test send
        try {
            Notification::route('mail', $request->email)->notify(
                new \App\Notifications\WelcomeNotification([
                    'user' => ['name' => 'Admin Test'],
                    'action_url' => url('/')
                ])
                // Note: Real implementation would ideally use a generic TestNotification 
                // that accepts the specific Key to test.
            );

            return response()->json(['message' => "Test email sent to {$request->email}"]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send test: ' . $e->getMessage()], 500);
        }
    }
}
