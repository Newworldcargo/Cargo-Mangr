<?php

namespace Modules\Users\Http\Filter;


/**
 * Use this class to add filter on users in query database.
 */
class UserFilter
{

    /**
     * user query.
     * @var object
     */
    public $query;

    /**
     * request data.
     * @var object
     */
    public $request;


    public function __construct($query, $request)
    {
        $this->query = $query;
        $this->request = $request;

        return $this;
    }

    /**
     * request data.
     * @param array $key_filters {
     * @item string
     * }
     * @return query
     */
    public function filterBy(...$key_filters)
    {
        $filter = $this->request->filter;
        $query = $this->query;

        if ($filter) {
            $filter_array = is_array($key_filters[0]) ? $key_filters[0] : $key_filters;
            foreach ($filter_array as $key) {

                // role
                if ($key == 'role') {
                    if (isset($filter['role']) && $filter['role'] != '') {
                        $query->whereIn('role', $filter['role']);
                    }
                }


                // name
                if ($key == 'name') {
                    if (isset($filter['name']) && $filter['name'] != '') {
                        $query->where('name', $filter['name']);
                    }
                }

                if ($key == 'branch_id' && !empty($filter['branch_id'])) {
                    $branchId = (int) $filter['branch_id'];
                    if ((int) auth()->user()->role !== \App\Models\User::ADMIN) {
                        $branchId = (int) \Modules\Cargo\Entities\Staff::where('user_id', auth()->id())->value('branch_id');
                    }
                    if (!$branchId) {
                        continue;
                    }
                    $query->where(function ($scope) use ($branchId) {
                        $scope->whereExists(function ($sub) use ($branchId) {
                            $sub->selectRaw('1')->from('branches')
                                ->whereColumn('branches.user_id', 'users.id')
                                ->where('branches.id', $branchId);
                        })->orWhereExists(function ($sub) use ($branchId) {
                            $sub->selectRaw('1')->from('staffs')
                                ->whereColumn('staffs.user_id', 'users.id')
                                ->where('staffs.branch_id', $branchId);
                        })->orWhereExists(function ($sub) use ($branchId) {
                            $sub->selectRaw('1')->from('clients')
                                ->whereColumn('clients.user_id', 'users.id')
                                ->where('clients.branch_id', $branchId);
                        })->orWhereExists(function ($sub) use ($branchId) {
                            $sub->selectRaw('1')->from('drivers')
                                ->whereColumn('drivers.user_id', 'users.id')
                                ->where('drivers.branch_id', $branchId);
                        });
                    });
                }

                if ($key == 'user_id' && !empty($filter['user_id'])) {
                    $query->where('users.id', (int) $filter['user_id']);
                }


                // check on created_at | filter table
                require app_path('Helpers/globalFilter/created_at.php');
            }
        }

        return $query;
    }
}
