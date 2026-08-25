<?php

namespace Modules\Cargo\Http\DataTables;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Button;
use Modules\Cargo\Entities\Shipment;
use Illuminate\Http\Request;
use Modules\Cargo\Http\Filter\ShipmentFilter;

class ShipmentsDataTable extends DataTable
{

    public $table_id = 'shipments';
    public $btn_exports = [
        'excel',
        'print',
        'pdf'
    ];
    public $filters = ['created_at', 'branch_id' ,'client_id' ,'payment_type' ,'payment_method_id' ,'paid' ,'shipping_date' , 'status_id' , 'captain_id' ];
    /**
     * Build DataTable class.
     *
     * @param  mixed  $query  Results from query() method.
     *
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->rawColumns(['action', 'select','id'])

            ->filterColumn('Shipment', function($query, $keyword) {
                $query->where('code', 'LIKE', "%$keyword%");
            })
            ->orderColumn('Shipment ', function ($query, $order) {
                $query->orderBy('code', $order);
            })

            ->editColumn('select', function (Shipment $model) {
                if($model->mission_id != null){
                    return '-';
                }else{
                    $adminTheme = env('ADMIN_THEME', 'adminLte');return view('cargo::'.$adminTheme.'.pages.shipments.columns.checkbox', ['model' => $model, 'ifHide' => $model->id == 0]);
                }
            })
            ->editColumn('type', function (Shipment $model) {
                return $model->type;
            })
            ->editColumn('branch_id', function (Shipment $model) {
                return $model->branch->name ?? "Null";
            })
            ->editColumn('client_id', function (Shipment $model) {
                return $model->client->name ?? "Null";
            })
            ->editColumn('shipping_cost', function (Shipment $model) {
                return format_price($model->tax + $model->shipping_cost + $model->insurance);
            })
            ->editColumn('payment_method_id', function (Shipment $model) {
                return $model->payment_method_id ?? "";
            })
            ->editColumn('paid', function (Shipment $model) {
                $receipt = $model->receipt;
                if ($receipt) {
                    if ($receipt->isRefundRequested()) {
                        return 'Refund Requested';
                    }
                    if ($receipt->isPartiallyRefunded()) {
                        return 'Partially Refunded';
                    }
                    if ($receipt->isRefunded()) {
                        return 'Refunded';
                    }
                    if ($receipt->status === 'partially_paid') {
                        return 'Partially Paid';
                    }
                    if ($receipt->status === 'completed') {
                        return __('cargo::view.paid');
                    }
                }

                return $model->paid == 1 ? __('cargo::view.paid') : '-';
            })
            ->editColumn('shipping_date', function (Shipment $model) {
                return $model->shipping_date;
            })
            ->editColumn('from_state_id', function (Shipment $model) {
                return $model->from_state_id ? $model->from_state->name : '';
            })
            ->editColumn('to_state_id', function (Shipment $model) {
                return $model->to_state_id ? $model->to_state->name : '';
            })
            ->editColumn('created_at', function (Shipment $model) {
                return date('d M, Y H:i', strtotime($model->created_at));
            })
            ->addColumn('action', function (Shipment $model) {
                $adminTheme = env('ADMIN_THEME', 'adminLte');return view('cargo::'.$adminTheme.'.pages.shipments.columns.actions', ['model' => $model, 'table_id' => $this->table_id]);
            });
    }

    /**
     * Get query source of dataTable.
     *
     * @param  Shipment  $model
     *
     * @return Shipment
     */
    public function query(Shipment $model, Request $request)
    {
        $query = $model->getShipments($model,$request)->newQuery();

        // class filter for user only
        $shipment_filter = new ShipmentFilter($query, $request);

        $query = $shipment_filter->filterBy($this->filters)->with('receipt');

        return $query;
    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\DataTables\Html\Builder
     */
    public function html()
    {
        $lang = \LaravelLocalization::getCurrentLocale();
        $lang = get_locale_name_by_code($lang, $lang);

        return $this->builder()
            ->setTableId($this->table_id)
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->stateSave(true)
            ->orderBy(1)
            ->responsive()
            ->autoWidth(false)
            ->parameters([
                'scrollX' => true,
                'dom' => 'Bfrtip',
                'bDestroy' => true,
                'language' => ['url' => "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/$lang.json"],
                'buttons' => [
                    ...$this->buttonsExport(),
                ],
            ])
            ->addTableClass('align-middle table-row-dashed fs-6 gy-5');
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns()
    {
        return [
            Column::computed('select')
                    ->title('
                        <div class="form-check form-check-sm form-check-custom form-check-solid">
                            <input class="form-check-input checkbox-all-rows" type="checkbox">
                        </div>
                    ')
                    ->responsivePriority(-1)
                    ->addClass('not-export')
                    ->width(50),
            Column::make('id')->title(__('cargo::view.table.#'))->width(50),
            Column::make('code')->title(__('cargo::view.table.code')),
            Column::make('type')->title(__('cargo::view.table.type')),
            Column::make('branch_id')->title(__('cargo::view.table.branch')),
            Column::make('client_id')->title(__('cargo::view.client')),
            Column::make('shipping_cost')->title(__('cargo::view.shipping_cost')),
            Column::make('payment_method_id')->title(__('cargo::view.payment_method')),
            Column::make('paid')->title(__('cargo::view.paid')),
            Column::make('from_state_id')->title(__('cargo::view.from_region')),
            Column::make('to_state_id')->title(__('cargo::view.to_region')),
            Column::make('shipping_date')->title(__('cargo::view.shipping_date')),
            Column::make('created_at')->title(__('view.created_at')),
            Column::computed('action')->title(__('view.action'))->addClass('text-center not-export')->responsivePriority(-1),
            Column::make('order_id')->visible(false),
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename()
    {
        return 'shipments_'.date('YmdHis');
    }


    /**
     * Transformer buttons export.
     *
     * @return string
     */
    protected function buttonsExport()
    {
        $btns = [];
        foreach($this->btn_exports as $btn) {
            $btns[] = [
                'extend' => $btn,
                'exportOptions' => [
                    'columns' => 'th:not(.not-export)'
                ]
            ];
        }
        return $btns;
    }
}
