<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\ContactsRequest;
use Illuminate\Support\Facades\DB;
use App\Models\Contact;
use Exception;

class ContactsController extends Controller
{
    /**
     * @store
     *
     * @param  mixed $request
     * @return void
     */
    public function store(ContactsRequest $request)
    {
        try {
            DB::beginTransaction();
            Contact::Create(
                [
                    'name' => $request->name,
                    'phone' => $request->mobile,
                    'email'   => $request->email,
                    'message' => $request->message
                ]
            );
            DB::commit();
            return response()->json(['message' => 'Your message has been sent successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @get all contact
     *
     * @param  mixed all contact list
     * @return void
     */
    public function view()
    {
        try {
            DB::beginTransaction();
            $contacts = Contact::get();
            DB::commit();
            return response()->json(['data' => $contacts]);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
}
