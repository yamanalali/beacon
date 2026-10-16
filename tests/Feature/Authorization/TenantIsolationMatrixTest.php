<?php
namespace Tests\Feature\Authorization;
use App\Ai\Models\AiEscalation; use App\Enums\UserRole; use App\Models\{Document,Enterprise,FinancialTransaction,ReceiptExtraction,Referral,ReadinessAssessment,FollowUpAction,MfiInstitution,User};
use Illuminate\Foundation\Testing\RefreshDatabase; use Illuminate\Support\Facades\Storage; use Tests\TestCase; use Tests\Feature\Readiness\ReadinessTestHelpers;
/**
 * Tenant isolation matrix (docs/tenant-isolation-audit.md): every route bound to an enterprise-owned
 * record is called with enterprise B's ids by A's SME, A's advisor and another institution's MFI user;
 * each must answer 403 or 404 (never 200/302/422).
 */
class TenantIsolationMatrixTest extends TestCase { use RefreshDatabase, ReadinessTestHelpers;
 public function test_every_enterprise_bound_route_refuses_other_tenants(): void { $failures = []; $this->withoutVite(); Storage::fake('local');
  $a=$this->enterprise('A'); $b=$this->enterprise('B'); $smeA=$this->smeOf($a); $smeB=$this->smeOf($b); $advA=$this->advisorOf($a);
  $inst=$this->institution(); $inst2=$this->institution('other'); $mfi=$this->mfiUserOf($inst2);
  $t=new FinancialTransaction; $t->forceFill(['enterprise_id'=>$b->id,'type'=>'sale','transaction_date'=>'2026-10-01','amount'=>'100','currency'=>'SYP','category'=>'c','payment_status'=>'unpaid','status'=>'posted'])->save();
  Storage::disk('local')->put('docs/b.jpg','x');
  $d=new Document; $d->forceFill(['enterprise_id'=>$b->id,'title'=>'r','original_name'=>'r.jpg','disk'=>'local','path'=>'docs/b.jpg','mime_type'=>'image/jpeg','size'=>1])->save();
  $x=new ReceiptExtraction; $x->forceFill(['enterprise_id'=>$b->id,'document_id'=>$d->id,'status'=>'pending_review','extractor'=>'fake'])->save();
  $rs=$this->publishedRuleSet([['code'=>'C1','weight'=>1]]);
  $as=ReadinessAssessment::query()->forceCreate(['public_id'=>(string)str()->ulid(),'enterprise_id'=>$b->id,'readiness_rule_set_id'=>$rs->id,'status'=>'draft']);
  $ref=Referral::query()->forceCreate(['public_id'=>(string)str()->ulid(),'enterprise_id'=>$b->id,'mfi_institution_id'=>$inst->id,'status'=>'shared','purpose_ar'=>'x','disclosed_items'=>[]]);
  $fu=FollowUpAction::query()->forceCreate(['enterprise_id'=>$b->id,'description'=>'d']);
  $esc=AiEscalation::query()->create(['user_id'=>$smeB->id,'enterprise_id'=>$b->id,'question'=>'q','status'=>'open']);
  $bind=['enterprise'=>$b->public_id,'transaction'=>$t->public_id,'document'=>$d->public_id,'extraction'=>$x->public_id??$x->id,'assessment'=>$as->public_id,'referral'=>$ref->public_id,'followUp'=>$fu->public_id??$fu->id,'escalation'=>$esc->public_id??$esc->id,'format'=>'csv','requirement'=>'1','result'=>'1'];
  foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) { $r = ['uri' => $route->uri(), 'method' => implode('|', $route->methods()), 'name' => $route->getName()];
   if (!preg_match('/\{(enterprise|transaction|document|extraction|assessment|referral|followUp|escalation)\}/',$r['uri'])) continue;
   $uri='/'.preg_replace_callback('/\{(\w+)\}/',fn($m)=>$bind[$m[1]]??'1',$r['uri']); $m=explode('|',$r['method'])[0];
   foreach (['smeA'=>$smeA,'advA'=>$advA,'mfi'=>$mfi] as $who=>$u) { $s=$this->actingAs($u)->call($m,$uri,[])->status();
     if (!in_array($s,[403,404])) $failures[] = "$who $s $m $uri {$r['name']}"; }
  } $this->assertSame([], $failures); } }
