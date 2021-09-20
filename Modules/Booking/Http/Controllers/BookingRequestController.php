<?php

namespace Modules\Booking\Http\Controllers;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Booking\Entities\BookingRequest;
use Modules\Booking\Http\Requests\BookingRequest as BookingFormRequest;
use Yajra\DataTables\Facades\DataTables;

class BookingRequestController extends Controller
{
    public $user;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = auth()->user();
            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($isTrashed = false)
    {

        if (is_null($this->user) || !$this->user->can('booking_request.view')) {
            $message = 'You are not allowed to access this page !';
            return view('errors.403', compact('message'));
        }

        if (request()->ajax()) {
            $status = empty(request()->status) ? 'pending' : request()->status;

            if ($isTrashed) {
                $booking_requests = BookingRequest::orderBy('id', 'desc')
                    ->where('status', $status)
                    ->get();
            } else {
                $booking_requests = BookingRequest::orderBy('id', 'desc')
                    ->where('status', $status)
                    ->get();
            }

            $datatable = DataTables::of($booking_requests, $isTrashed)
                ->addIndexColumn()
                ->addColumn(
                    'action',
                    function ($row) use ($isTrashed) {
                        $csrf = "" . csrf_field() . "";
                        $method_delete = "" . method_field("delete") . "";
                        $method_put = "" . method_field("put") . "";
                        $html = "";

                        $deleteRoute =  route('admin.booking_request.delete', [$row->id]);
                        if ($this->user->can('booking_request.edit')) {
                            $html = '<a class="btn waves-effect waves-light btn-success btn-sm btn-circle" title="View & Edit Request Details" href="' . route('admin.booking_request.edit', $row->id) . '"><i class="fa fa-eye"></i></a>';
                        }

                        if ($this->user->can('booking_request.delete')) {
                            $html .= '<a class="btn waves-effect waves-light btn-danger btn-sm btn-circle ml-2 text-white" title="Delete Admin" id="deleteItem' . $row->id . '"><i class="fa fa-trash"></i></a>';
                        }

                        $html .= '<script>
                            $("#deleteItem' . $row->id . '").click(function(){
                                swal.fire({ title: "Are you sure?",text: "Request will be deleted as trashed !",type: "warning",showCancelButton: true,confirmButtonColor: "#DD6B55",confirmButtonText: "Yes, delete it!"
                                }).then((result) => { if (result.value) {$("#deleteForm' . $row->id . '").submit();}})
                            });
                        </script>';

                        $html .= '<script>
                            $("#deleteItemPermanent' . $row->id . '").click(function(){
                                swal.fire({ title: "Are you sure?",text: "Request will be deleted permanently, both from trash !",type: "warning",showCancelButton: true,confirmButtonColor: "#DD6B55",confirmButtonText: "Yes, delete it!"
                                }).then((result) => { if (result.value) {$("#deletePermanentForm' . $row->id . '").submit();}})
                            });
                        </script>';

                        $html .= '<script>
                            $("#revertItem' . $row->id . '").click(function(){
                                swal.fire({ title: "Are you sure?",text: "Request will be revert back from trash !",type: "warning",showCancelButton: true,confirmButtonColor: "#DD6B55",confirmButtonText: "Yes, Revert Back!"
                                }).then((result) => { if (result.value) {$("#revertForm' . $row->id . '").submit();}})
                            });
                        </script>';

                        $html .= '
                            <form id="deleteForm' . $row->id . '" action="' . $deleteRoute . '" method="post" style="display:none">' . $csrf . $method_delete . '
                                <button type="submit" class="btn waves-effect waves-light btn-rounded btn-success"><i
                                        class="fa fa-check"></i> Confirm Delete</button>
                                <button type="button" class="btn waves-effect waves-light btn-rounded btn-secondary" data-dismiss="modal"><i
                                        class="fa fa-times"></i> Cancel</button>
                            </form>';

                        $html .= '
                            <form id="deletePermanentForm' . $row->id . '" action="' . $deleteRoute . '" method="post" style="display:none">' . $csrf . $method_delete . '
                                <button type="submit" class="btn waves-effect waves-light btn-rounded btn-success"><i
                                        class="fa fa-check"></i> Confirm Permanent Delete</button>
                                <button type="button" class="btn waves-effect waves-light btn-rounded btn-secondary" data-dismiss="modal"><i
                                        class="fa fa-times"></i> Cancel</button>
                            </form>';
                        return $html;
                    }
                )

                ->editColumn('status', function ($row) {
                    $statusText = ucwords($row->status);
                    if ($row->status === 'pending') {
                        return '<span class="badge badge-warning font-weight-100">'.$statusText.'</span>';
                    } else if ($row->status === 'completed') {
                        return '<span class="badge badge-success">'.$statusText.'</span>';
                    }  else if ($row->status === 'cancelled') {
                        return '<span class="badge badge-danger">'.$statusText.'</span>';
                    } else {
                        return '<span class="badge badge-info">'.$statusText.'</span>';
                    }
                });

            $rawColumns = ['name', 'email', 'phone_no', 'service_name', 'start_date', 'status', 'action'];

            return $datatable->rawColumns($rawColumns)->make(true);
        }

        $count_booking_requests             = BookingRequest::select('id')->count();
        $count_pending_booking_requests     = BookingRequest::select('id')->where('status', 'pending')->count();
        $count_processing_booking_requests  = BookingRequest::select('id')->where('status', 'processing')->count();
        $count_completed_booking_requests   = BookingRequest::select('id')->where('status', 'completed')->count();
        $count_cancelled_booking_requests   = BookingRequest::select('id')->where('status', 'cancelled')->count();

        return view('booking::backend.booking_request.index', compact('count_booking_requests', 'count_pending_booking_requests', 'count_processing_booking_requests', 'count_cancelled_booking_requests', 'count_completed_booking_requests'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  $request
     * @return \Illuminate\Http\Response
     */
    public function store(BookingFormRequest $request)
    {
        try {
            BookingRequest::store($request->all());
            session()->flash('success', 'Your request has been sent to authority. An agent will communicate with you soon.');
            return back();
        } catch (Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (is_null($this->user) || !$this->user->can('booking_request.edit')) {
            $message = 'You are not allowed to access this page !';
            return view('errors.403', compact('message'));
        }

        $booking_request = BookingRequest::find($id);

        return view('booking::backend.booking_request.edit', compact('booking_requests'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (is_null($this->user) || !$this->user->can('booking_request.edit')) {
            $message = 'You are not allowed to access this page !';
            return view('errors.403', compact('message'));
        }

        $booking_request = BookingRequest::find($id);

        if (is_null($booking_request)) {
            session()->flash('error', "The page is not found !");
            return redirect()->route('admin.booking_request.index');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (is_null($this->user) || !$this->user->can('booking_request.delete')) {
            $message = 'You are not allowed to access this page !';
            return view('errors.403', compact('message'));
        }

        $booking_request = BookingRequest::find($id);
        if (is_null($booking_request)) {
            session()->flash('error', "The page is not found !");
            return redirect()->route('admin.booking_request.trashed');
        }

        $booking_request->status = 'cancelled';
        $booking_request->deleted_by = auth()->user()->id;
        $booking_request->status = 0;
        $booking_request->save();

        session()->flash('success', 'Request has been deleted successfully as trashed !!');
        return redirect()->route('admin.booking_request.trashed');
    }

    /**
     * destroyTrash
     *
     * @param integer $id
     * @return void Destroy the data permanently
     */
    public function destroyTrash($id)
    {
        if (is_null($this->user) || !$this->user->can('booking_request.delete')) {
            $message = 'You are not allowed to access this page !';
            return view('errors.403', compact('message'));
        }
        $booking_request = BookingRequest::find($id);
        if (is_null($booking_request)) {
            session()->flash('error', "The page is not found !");
            return redirect()->route('admin.booking_request.trashed');
        }

        // Delete Category permanently
        $booking_request->delete();

        session()->flash('success', 'Request has been deleted permanently !!');
        return redirect()->route('admin.booking_request.trashed');
    }

    /**
     * trashed
     *
     * @return view the trashed data list -> which data status = 0 and deleted_at != null
     */
    public function trashed()
    {
        if (is_null($this->user) || !$this->user->can('booking_request.view')) {
            $message = 'You are not allowed to access this page !';
            return view('errors.403', compact('message'));
        }

        return $this->index(true);
    }
}
