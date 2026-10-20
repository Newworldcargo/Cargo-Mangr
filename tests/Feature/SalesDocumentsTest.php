<?php

namespace Tests\Feature;

use App\Models\{User, MessagingSetting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Queue, Mail};
use Illuminate\Support\Str;
use Modules\Billing\Jobs\SendSalesDocument;
use Modules\Billing\Services\{InvoiceLedger, DocumentPdf};
use Modules\Cargo\Entities\{Branch, Client};
use Tests\TestCase;

class SalesDocumentsTest extends TestCase
{
    use RefreshDatabase;
    private $branch;
    private $client;
    private $taxId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::create(['name'=>'Sales Admin','email'=>'sales@test.example','password'=>bcrypt('test'),'role'=>1]));
        $this->branch = Branch::create(['name'=>'Test Lusaka','email'=>'branch@test.example','code'=>1,'user_id'=>auth()->id(),'default_currency'=>'ZMW']);
        $this->client = Client::create(['name'=>'Test Customer','email'=>'customer@test.example','code'=>1,'user_id'=>auth()->id(),'branch_id'=>$this->branch->id]);
        DB::table('billing_profiles')->insert(['branch_id'=>$this->branch->id,'legal_name'=>'Test Cargo','address'=>'Test billing address','vat_status'=>'not_registered']);
        $this->taxId=DB::table('billing_tax_rates')->insertGetId(['branch_id'=>$this->branch->id,'label'=>'Non-VAT','category'=>'non_vat','basis_points'=>0,'effective_from'=>'2000-01-01','active'=>true]);
        DB::table('currencies')->where('code','USD')->update(['default'=>1]);
        config(['messaging.activation_ready'=>true]);
        MessagingSetting::updateOrCreate(['id'=>1],['email_enabled'=>true]);
    }

    private function input(): array
    {
        return ['request_key'=>(string)Str::uuid(),'branch_id'=>$this->branch->id,'client_id'=>$this->client->id,
            'issue_date'=>now()->toDateString(),'due_date'=>now()->addDays(14)->toDateString(),
            'items'=>[['description'=>'Delivery','quantity'=>'1','unit_price'=>'100','discount'=>'0','tax_percent'=>'0','tax_id'=>$this->taxId,'tax_mode'=>'exclusive','unit'=>'service']], 'terms'=>'Payment due in 14 days.'];
    }

    public function test_tax_inclusive_preview_uses_configured_tax_and_three_decimal_quantity(): void
    {
        DB::table('billing_profiles')->where('branch_id',$this->branch->id)->update(['vat_status'=>'registered','tpin'=>'TEST-TPIN']);
        DB::table('billing_tax_rates')->where('id',$this->taxId)->update(['category'=>'standard','label'=>'Test VAT','basis_points'=>1600]);
        $data=$this->input();$data['items'][0]=array_merge($data['items'][0],['quantity'=>'0.125','unit_price'=>'116','tax_percent'=>'99','tax_mode'=>'inclusive','unit'=>'kg']);
        $this->postJson(route('billing.preview'),$data)->assertOk()->assertJsonPath('total_minor',1450)->assertJsonPath('tax_minor',200)->assertJsonPath('subtotal_minor',1250);
        $data['details']=['sales_agent_id'=>auth()->id(),'order_reference'=>'PO-123','customer_address'=>'Customer address','customer_tpin'=>'CLIENT-TPIN',
            'customer_notes'=>'Delivery note for customer','internal_notes'=>'PRIVATE TEST NOTE','payment_terms'=>'net30'];
        $this->post(route('billing.documents.store',['type'=>'invoices']),$data)->assertSessionHasNoErrors();
        $invoice=DB::table('billing_invoices')->first();$item=DB::table('billing_invoice_items')->first();
        $this->assertSame(125,(int)$item->quantity_thousandths);$this->assertSame(1600,(int)$item->tax_basis_points);
        $this->assertSame(now()->addDays(30)->toDateString(),$invoice->due_date);
        $this->action($invoice->id,'issue')->assertSessionHasNoErrors();
        $html=view('billing::pdf',['document'=>$invoice,'type'=>'invoices','items'=>[$item]])->render();
        $this->assertStringContainsString('0.125 kg',$html);$this->assertStringContainsString('PO-123',$html);
        $this->assertStringContainsString('TEST-TPIN',$html);$this->assertStringNotContainsString('PRIVATE TEST NOTE',$html);
        $bytes=app(DocumentPdf::class)->get('invoices',$invoice->id);
        DB::table('billing_tax_rates')->where('id',$this->taxId)->update(['basis_points'=>1700]);
        $this->assertSame($bytes,app(DocumentPdf::class)->get('invoices',$invoice->id));
        $this->get(route('billing.invoices.index',['q'=>'Test Customer','agent'=>'Sales Admin']))->assertOk()->assertSee($invoice->number??'NWC-INV');
        DB::table('billing_tax_rates')->where('id',$this->taxId)->update(['active'=>false]);
        $this->postJson(route('billing.preview'),$data)->assertUnprocessable()->assertJsonValidationErrors('items.0.tax_id');
    }

    public function test_sales_configuration_crud_permissions_and_invalid_tax_combinations(): void
    {
        $profile=['branch_id'=>$this->branch->id,'legal_name'=>'Configured Company','address'=>'Registered address','vat_status'=>'registered','tpin'=>'TEST-TPIN'];
        $this->post(route('billing.settings.save',['section'=>'profile']),$profile)->assertSessionHasNoErrors();
        $tax=['branch_id'=>$this->branch->id,'label'=>'Exempt service','category'=>'exempt','rate'=>'16','effective_from'=>'2026-01-01','active'=>1];
        $this->post(route('billing.settings.save',['section'=>'tax']),$tax)->assertSessionHasErrors('settings');
        $tax['rate']='0';$this->post(route('billing.settings.save',['section'=>'tax']),$tax)->assertSessionHasNoErrors();
        $service=['branch_id'=>$this->branch->id,'name'=>'Service test','unit'=>'kg','price'=>'20','tax_id'=>$this->taxId,'tax_mode'=>'exclusive','active'=>1];
        $this->post(route('billing.settings.save',['section'=>'service']),$service)->assertSessionHasNoErrors();
        $this->getJson(route('billing.catalogue',['branch_id'=>$this->branch->id]))->assertOk()->assertJsonFragment(['name'=>'Service test','price_minor'=>2000]);
        $settings=$this->get(route('billing.settings',['branch_id'=>$this->branch->id]))->assertOk()->assertSee('Configured Company');
        if(getenv('NWC_SALES_PREVIEW')==='1')file_put_contents('/tmp/nwc-sales-settings.html',$settings->getContent());
        $this->actingAs(User::create(['name'=>'Restricted','email'=>'restricted@test.example','password'=>bcrypt('test'),'role'=>0]));
        $this->post(route('billing.settings.save',['section'=>'profile']),$profile)->assertForbidden();
        $this->get(route('billing.settings'))->assertForbidden();
    }

    public function test_draft_requires_review_after_tax_change_and_scope_cannot_be_bypassed(): void
    {
        $id=$this->create();
        DB::table('billing_tax_rates')->where('id',$this->taxId)->update(['active'=>false]);
        $this->action($id,'issue')->assertSessionHasErrors('items.0.tax_id');
        $this->assertSame('draft',DB::table('billing_invoices')->find($id)->status);
        $other=DB::table('billing_tax_rates')->insertGetId(['branch_id'=>9999,'label'=>'Other branch','category'=>'non_vat','basis_points'=>0,'effective_from'=>'2000-01-01','active'=>true]);
        $data=$this->input();$data['items'][0]['tax_id']=$other;
        $this->postJson(route('billing.preview'),$data)->assertUnprocessable()->assertJsonValidationErrors('items.0.tax_id');
        $this->post(route('billing.documents.store',['type'=>'invoices']),$data)->assertSessionHasErrors('items.0.tax_id');
        $this->assertSame(1,DB::table('billing_invoices')->count());
    }

    public function test_branch_currency_change_cannot_relabel_existing_draft_prices(): void
    {
        $id=$this->create();$this->branch->update(['default_currency'=>'USD']);
        $this->put(route('billing.documents.update',['type'=>'invoices','id'=>$id]),$this->input()+['revision'=>1])->assertSessionHasErrors('branch_id');
        $this->assertDatabaseHas('billing_invoices',['id'=>$id,'currency'=>'ZMW','total_minor'=>10000]);
    }

    public function test_customer_search_matches_reordered_names_and_unassigned_customers_can_be_billed(): void
    {
        $this->client->update(['name'=>'Alex  Martin Tembo', 'branch_id'=>null]);
        foreach (['martin alex tembo',' Tembo   Martin ', 'alex'] as $term) {
            $this->getJson(route('billing.customers', ['q'=>$term,'branch_id'=>$this->branch->id]))
                ->assertOk()->assertJsonFragment(['id'=>$this->client->id]);
        }
        $this->create();
        $this->assertDatabaseHas('billing_invoices',['client_id'=>$this->client->id,'branch_id'=>$this->branch->id]);
        $this->assertNull($this->client->fresh()->branch_id);
        $this->client->update(['is_archived'=>1]);
        $this->getJson(route('billing.customers',['q'=>'martin tembo','branch_id'=>$this->branch->id]))->assertExactJson([]);
    }

    public function test_shipment_details_survive_edit_issue_and_quotation_conversion(): void
    {
        $shipping = ['shipment_type'=>'pickup','shipping_date'=>now()->toDateString(),'collection_time'=>'09:30',
            'sender_phone'=>'+260971234567','sender_address'=>'Lusaka','receiver_name'=>'Receiver','receiver_phone'=>'+260971234568',
            'receiver_address'=>'Kitwe','from_country'=>'Zambia','to_country'=>'Zambia','payment_type'=>'postpaid','payment_method'=>'cash',
            'packages'=>[['type'=>'Box','description'=>'Clothing','quantity'=>2,'weight'=>'1.5','length'=>10,'width'=>20,'height'=>30]],
            'total_weight_kg'=>'999','source_shipment_id'=>123];
        $data = $this->input()+['document_mode'=>'shipment','shipment_details'=>$shipping];
        $count = DB::table('shipments')->count();
        $this->post(route('billing.documents.store',['type'=>'quotations']),$data)->assertSessionHasNoErrors()->assertRedirect();
        $id = DB::table('billing_quotations')->max('id');
        $stored = json_decode(DB::table('billing_quotations')->find($id)->shipment_details,true);
        $this->assertSame('3.000',$stored['total_weight_kg']);
        $this->assertArrayNotHasKey('source_shipment_id',$stored);
        $this->get(route('billing.documents.edit',['type'=>'quotations','id'=>$id]))->assertOk()->assertSee('Clothing');
        $this->action($id,'issue',[],'quotations')->assertSessionHasNoErrors();
        $this->action($id,'accept',['note'=>'Customer accepted by email.'],'quotations')->assertSessionHasNoErrors();
        $this->action($id,'convert',[],'quotations')->assertSessionHasNoErrors();
        $invoice = DB::table('billing_invoices')->first();
        $this->assertSame($stored,json_decode($invoice->shipment_details,true));
        $this->get(route('billing.documents.print',['type'=>'invoices','id'=>$invoice->id]))->assertOk()->assertSee('Clothing')->assertSee('3.000 kg');
        $this->assertSame($count,DB::table('shipments')->count());
        $this->assertSame(0,DB::table('billing_payments')->count());
        $invalid=$this->input()+['document_mode'=>'shipment','shipment_details'=>[]];
        $this->post(route('billing.documents.store',['type'=>'invoices']),$invalid)->assertSessionHasErrors('shipment_details.receiver_name');
        $plain=$this->input()+['document_mode'=>'plain','shipment_details'=>['bad'=>'ignored']];
        $this->post(route('billing.documents.store',['type'=>'invoices']),$plain)->assertSessionHasNoErrors();
        $this->assertNull(DB::table('billing_invoices')->orderByDesc('id')->first()->shipment_details);
    }

    public function test_unassigned_customer_search_does_not_remove_staff_branch_boundaries(): void
    {
        $staff=User::create(['name'=>'Sales staff','email'=>'scoped@test.example','password'=>bcrypt('test'),'role'=>0]);
        $staff->givePermissionTo('view-billing-invoices');
        $this->branch->update(['user_id'=>$staff->id]);
        $this->client->update(['name'=>'Alex Martin Tembo','branch_id'=>null]);
        $this->actingAs($staff);
        $url=route('billing.customers',['q'=>'martin alex','branch_id'=>$this->branch->id]);
        $this->getJson($url)->assertExactJson([]);
        $this->getJson(route('billing.customers',['q'=>'martin','branch_id'=>99999]))->assertForbidden();
        \Modules\Cargo\Entities\Shipment::create(['code'=>'SALES-TEST-1','client_id'=>$this->client->id,'branch_id'=>$this->branch->id,
            'status_id'=>1,'type'=>1,'shipping_date'=>now()->toDateString(),'client_phone'=>'260971234567',
            'from_country_id'=>1,'from_state_id'=>1,'to_country_id'=>1,'to_state_id'=>1]);
        $this->getJson($url)->assertOk()->assertJsonFragment(['id'=>$this->client->id]);
        $this->getJson(route('billing.shipments',['q'=>'SALES-TEST','branch_id'=>$this->branch->id]))->assertExactJson([]);
        $staff->update(['role'=>1]);
        $this->getJson(route('billing.shipments',['q'=>'SALES-TEST','branch_id'=>$this->branch->id]))->assertOk()->assertJsonFragment(['code'=>'SALES-TEST-1']);
    }

    public function test_customer_modal_creation_rejects_duplicate_contacts_and_is_audited(): void
    {
        $data = ['type'=>'invoices','branch_id'=>$this->branch->id,'first_name'=>'New','last_name'=>'Customer',
            'email'=>'new.customer@example.com','phone'=>'+260971234567'];
        $response = $this->postJson(route('billing.customers.store'), $data)->assertCreated()->assertJsonPath('name', 'New Customer');
        $this->assertDatabaseHas('clients', ['id'=>$response->json('id'),'responsible_mobile'=>'260971234567']);
        $this->assertDatabaseHas('audit_logs', ['event'=>'sales_customer_created','auditable_id'=>$response->json('id')]);
        $count = User::count();
        $this->postJson(route('billing.customers.store'), array_merge($data,['email'=>'another@example.com','phone'=>'0971234567']))->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson(route('billing.customers.store'), array_merge($data,['phone'=>'0971234568']))->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame($count, User::count());
        $this->actingAs(User::create(['name'=>'No permission','email'=>'none@example.com','password'=>bcrypt('test'),'role'=>0]));
        $this->postJson(route('billing.customers.store'), $data)->assertForbidden();
    }

    private function create(string $type='invoices'): int
    {
        $this->post(route('billing.documents.store',['type'=>$type]),$this->input())->assertSessionHasNoErrors()->assertRedirect();
        return DB::table('billing_'.$type)->max('id');
    }

    private function action(int $id, string $action, array $data=[], string $type='invoices')
    {
        return $this->post(route('billing.documents.action',compact('type','id','action')),$data);
    }

    public function test_invoice_create_edit_issue_partial_full_payment_and_pdf(): void
    {
        $form = $this->get(route('billing.documents.create',['type'=>'invoices']))->assertOk()->assertSee('New invoice');
        if (getenv('NWC_SALES_PREVIEW') === '1') file_put_contents('/tmp/nwc-sales-form.html', $form->getContent());
        $id=$this->create();
        $params=['type'=>'invoices','id'=>$id];
        $this->get(route('billing.documents.show',$params))->assertOk()->assertSee('Test Customer');
        $data=$this->input()+['revision'=>1];
        $data['items'][0]['unit_price']='120';
        $this->put(route('billing.documents.update',$params),$data)->assertSessionHasNoErrors();
        $this->put(route('billing.documents.update',$params),$data)->assertSessionHasErrors('document');
        $this->action($id,'issue')->assertSessionHasNoErrors();
        $this->get(route('billing.documents.edit',$params))->assertStatus(409);
        $this->assertSame(12000,(int)DB::table('billing_invoices')->find($id)->total_minor);
        $bytes=$this->get(route('billing.documents.download',$params))->assertOk()->assertHeader('Content-Type','application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF-', $bytes);
        if (getenv('NWC_SALES_PREVIEW') === '1') {
            file_put_contents('/tmp/nwc-sales-document.html', $this->get(route('billing.documents.show',$params))->getContent());
            file_put_contents('/tmp/nwc-sales-document.pdf', $bytes);
        }
        $payment=['amount'=>'20','method'=>'cash','paid_on'=>now()->toDateString(),'request_key'=>(string)Str::uuid()];
        $this->action($id,'payment',$payment)->assertSessionHasNoErrors();
        $this->action($id,'payment',$payment)->assertSessionHasNoErrors();
        $this->assertSame(1,DB::table('billing_payments')->count());
        $this->assertSame('partially_paid',DB::table('billing_invoices')->find($id)->status);
        $this->assertSame($bytes,app(DocumentPdf::class)->get('invoices',$id));
        $this->get(route('billing.documents.receipt',['id'=>$id,'payment'=>DB::table('billing_payments')->value('id')]))->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->action($id,'void',['note'=>'Incorrect details'])->assertSessionHasErrors('document');
        $payment['amount']='100';$payment['request_key']=(string)Str::uuid();
        $this->action($id,'payment',$payment)->assertSessionHasNoErrors();
        $this->assertSame('paid',DB::table('billing_invoices')->find($id)->status);
    }

    public function test_quotation_acceptance_and_conversion_are_separate_and_idempotent(): void
    {
        $id=$this->create('quotations');
        $this->action($id,'convert',[],'quotations')->assertSessionHasErrors('document');
        $this->action($id,'issue',[],'quotations')->assertSessionHasNoErrors();
        $this->action($id,'accept',[],'quotations')->assertSessionHasErrors('document');
        $this->action($id,'accept',['note'=>'Customer confirmed by email today.'],'quotations')->assertSessionHasNoErrors();
        $this->action($id,'convert',[],'quotations')->assertSessionHasNoErrors();
        $this->action($id,'convert',[],'quotations')->assertSessionHasNoErrors();
        $this->assertSame(1,DB::table('billing_invoices')->count());
        $this->assertSame('converted',DB::table('billing_quotations')->find($id)->status);
        $this->assertSame('draft',DB::table('billing_invoices')->first()->status);
    }

    public function test_duplicate_is_a_new_unpaid_draft(): void
    {
        $id=$this->create();$this->action($id,'issue');
        $key=(string)Str::uuid();$params=['type'=>'invoices','id'=>$id];
        $this->post(route('billing.documents.duplicate',$params),['request_key'=>$key])->assertRedirect();
        $this->post(route('billing.documents.duplicate',$params),['request_key'=>$key])->assertRedirect();
        $this->assertSame(2,DB::table('billing_invoices')->count());
        $this->assertSame('draft',DB::table('billing_invoices')->orderByDesc('id')->first()->status);
    }

    public function test_staff_cannot_access_documents_or_customer_search_without_permissions(): void
    {
        $id=$this->create();
        $staff=User::create(['name'=>'Staff','email'=>'staff@test.example','password'=>bcrypt('test'),'role'=>0]);
        $this->actingAs($staff);
        $params=['type'=>'invoices','id'=>$id];
        $this->get(route('billing.documents.show',$params))->assertForbidden();
        $this->get(route('billing.documents.download',$params))->assertForbidden();
        $this->action($id,'issue')->assertForbidden();
        $this->get(route('billing.customers',['q'=>'Test','branch_id'=>$this->branch->id]))->assertForbidden();
        $staff->givePermissionTo('view-billing-invoices');
        $this->get(route('billing.documents.show',$params))->assertNotFound();
    }

    public function test_email_queue_and_paid_reminder_suppression(): void
    {
        Queue::fake();Mail::fake();
        $id=$this->create();$this->action($id,'issue');
        $params=['type'=>'invoices','id'=>$id];
        $key=(string)Str::uuid();
        $payload=['purpose'=>'document','request_key'=>$key];
        $this->post(route('billing.documents.delivery',$params),$payload)->assertSessionHasNoErrors();
        $this->post(route('billing.documents.delivery',$params),$payload)->assertSessionHasNoErrors();
        $this->assertSame(1,DB::table('billing_deliveries')->count());
        Queue::assertPushed(SendSalesDocument::class,1);
        $this->post(route('billing.documents.delivery',$params),[
            'purpose'=>'reminder','request_key'=>(string)Str::uuid(),'send_at'=>now()->addDay()->timezone('Africa/Lusaka')->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();
        $reminder=DB::table('billing_deliveries')->where('purpose','reminder')->first();
        DB::table('billing_deliveries')->where('id',$reminder->id)->update(['send_at'=>now()->subMinute()]);
        $this->action($id,'payment',['amount'=>'100','method'=>'cash','paid_on'=>now()->toDateString(),'request_key'=>(string)Str::uuid()])->assertSessionHasNoErrors();
        (new SendSalesDocument($reminder->id))->handle();
        $this->assertSame('suppressed',DB::table('billing_deliveries')->find($reminder->id)->status);
        Mail::assertNothingSent();
    }

    public function test_document_email_is_sent_once_and_failures_are_not_blindly_retried(): void
    {
        Queue::fake();
        $id=$this->create();$this->action($id,'issue');
        $this->post(route('billing.documents.delivery',['type'=>'invoices','id'=>$id]),['purpose'=>'document','request_key'=>(string)Str::uuid()])->assertSessionHasNoErrors();
        $delivery=DB::table('billing_deliveries')->first();
        Mail::shouldReceive('send')->once()->andReturnNull();
        (new SendSalesDocument($delivery->id))->handle();
        (new SendSalesDocument($delivery->id))->handle();
        $this->assertSame('accepted',DB::table('billing_deliveries')->find($delivery->id)->status);
    }

    public function test_cancelled_reminder_is_not_sent(): void
    {
        Queue::fake();Mail::fake();
        $id=$this->create();$this->action($id,'issue');
        $params=['type'=>'invoices','id'=>$id];
        $this->post(route('billing.documents.delivery',$params),['purpose'=>'reminder','request_key'=>(string)Str::uuid(),'send_at'=>now()->addDay()->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $delivery=DB::table('billing_deliveries')->first();
        $this->post(route('billing.documents.delivery.cancel',$params+['delivery'=>$delivery->id]))->assertRedirect();
        (new SendSalesDocument($delivery->id))->handle();
        $this->assertSame('cancelled',DB::table('billing_deliveries')->find($delivery->id)->status);
        Mail::assertNothingSent();
    }

    public function test_sales_lists_reuse_table_controls_and_paginate_on_the_server(): void
    {
        for ($i=0;$i<12;$i++) $this->create();
        $response=$this->get(route('billing.invoices.index',['per_page'=>10,'status'=>'draft']))
            ->assertOk()->assertSee('table-row-dashed')->assertSee('btn-action-table')
            ->assertSee('Showing 1 to 10 of 12 entries')
            ->assertViewHas('records',fn($records)=>$records->count()===10 && $records->perPage()===10);
        $this->get(route('billing.invoices.index',['per_page'=>10,'status'=>'draft','page'=>2]))
            ->assertOk()->assertSee('Showing 11 to 12 of 12 entries');
        $this->get(route('billing.invoices.index',['per_page'=>-1]))->assertSessionHasErrors('per_page');
        $this->create('quotations');
        $quotes=$this->get(route('billing.quotations.index'))->assertOk()->assertSee('Valid until')->assertSee('btn-action-table');
        if(getenv('NWC_SALES_PREVIEW')==='1') {
            file_put_contents('/tmp/nwc-sales-invoices-list.html',$response->getContent());
            file_put_contents('/tmp/nwc-sales-quotations-list.html',$quotes->getContent());
        }
    }
}
