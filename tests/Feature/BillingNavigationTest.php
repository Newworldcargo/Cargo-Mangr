<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BillingNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsRole(int $role): void
    {
        $this->actingAs(User::create([
            'name' => 'Sales Test', 'email' => 'sales@example.test',
            'password' => bcrypt('test'), 'role' => $role,
        ]));
        DB::table('currencies')->where('code', 'USD')->update(['default' => 1]);
    }

    public function test_admin_can_open_both_menu_destinations(): void
    {
        $this->loginAsRole(User::ADMIN);
        $this->get(route('billing.invoices.index'))->assertOk()->assertSee('No invoices yet.')->assertSee('Sales');
        $this->get(route('billing.quotations.index'))->assertOk()->assertSee('No quotations yet.');
        $menu = view('billing::components.aside_menu')->render();
        $this->assertStringContainsString(route('billing.invoices.index'), $menu);
        $this->assertStringContainsString(route('billing.quotations.index'), $menu);
    }

    public function test_staff_without_permissions_cannot_open_lists_or_see_menu(): void
    {
        $this->loginAsRole(User::STAFF);
        $this->get(route('billing.invoices.index'))->assertForbidden();
        $this->get(route('billing.quotations.index'))->assertForbidden();
        $this->assertStringNotContainsString('Sales menu', view('billing::components.aside_menu')->render());
    }

    public function test_customers_cannot_open_admin_sales(): void
    {
        $this->loginAsRole(4);
        $this->get(route('billing.invoices.index'))->assertForbidden();
        $this->get(route('billing.quotations.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('billing.invoices.index'))->assertRedirect();
    }

    public function test_finance_only_user_sees_transactions_under_sales(): void
    {
        $this->loginAsRole(User::STAFF);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name'=>'access-finance-transactions','guard_name'=>'web'], ['permission_group_id'=>DB::table('permission_groups')->where('name','sales')->value('id')]);
        auth()->user()->givePermissionTo('access-finance-transactions');
        $menu=view('billing::components.aside_menu')->render();
        $this->assertStringContainsString('Sales menu',$menu);
        $this->assertStringContainsString(route('transxn.index'),$menu);
        $this->assertStringNotContainsString('>Invoices</p>',$menu);
        $this->assertStringNotContainsString('>Refunds</p>',$menu);
    }
}
