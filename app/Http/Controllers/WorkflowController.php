<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\DocumentController;

class WorkflowController extends Controller
{
    protected DocumentController $documentController;

    public function __construct(DocumentController $documentController)
    {
        $this->documentController = $documentController;
    }

    /**
     * Display the workflow page for a document.
     */
    public function show($id)
    {
        return $this->documentController->workflowView($id);
    }

    /**
     * Handle workflow status actions.
     */
    public function action(Request $request, $id)
    {
        return $this->documentController->workflowAction($request, $id);
    }
}
