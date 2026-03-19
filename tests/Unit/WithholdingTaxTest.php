<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\PayrollDeductionPolicy;
use App\Models\WithholdingTaxPolicy;
use App\Services\Payroll\PayrollCalculator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class WithholdingTaxTest extends TestCase
{
    public function test_progressive_withholding_sums_brackets_when_base_tax_is_zero(): void
    {
        $policy = new WithholdingTaxPolicy();
        $policy->enabled = true;
        $policy->method = 'table';
        $policy->pay_frequency = 'monthly';
        $policy->timing = 'per_pay_period';
        $policy->split_rule = 'monthly';

        $brackets = new Collection([
            (object) ['pay_frequency' => 'monthly', 'bracket_from' => 0.00, 'bracket_to' => 20833.00, 'base_tax' => 0.00, 'excess_percent' => 0.00],
            (object) ['pay_frequency' => 'monthly', 'bracket_from' => 20833.00, 'bracket_to' => 33332.00, 'base_tax' => 0.00, 'excess_percent' => 15.00],
            (object) ['pay_frequency' => 'monthly', 'bracket_from' => 33333.00, 'bracket_to' => 66666.00, 'base_tax' => 0.00, 'excess_percent' => 20.00],
            (object) ['pay_frequency' => 'monthly', 'bracket_from' => 66667.00, 'bracket_to' => null, 'base_tax' => 0.00, 'excess_percent' => 25.00],
        ]);

        $calc = new PayrollCalculator();
        $ref = new ReflectionClass($calc);
        $m = $ref->getMethod('computeWithholdingTax');
        $m->setAccessible(true);

        $taxable = 50000.00;
        $amount = (float) $m->invoke($calc, $policy, $brackets, $taxable, '11-25');

        // (33333 - 20833) * 15% + (50000 - 33333) * 20% = 5208.40
        $this->assertEqualsWithDelta(5208.40, $amount, 0.00001);
    }

    public function test_cutoff1_only_rule_applies_tax_only_on_first_cutoff(): void
    {
        $policy = new WithholdingTaxPolicy();
        $policy->enabled = true;
        $policy->method = 'table';
        $policy->pay_frequency = 'semi_monthly';
        $policy->timing = 'monthly';
        $policy->split_rule = 'cutoff1_only';

        $brackets = new Collection([
            (object) ['pay_frequency' => 'monthly', 'bracket_from' => 0.00, 'bracket_to' => 20833.00, 'base_tax' => 0.00, 'excess_percent' => 0.00],
            (object) ['pay_frequency' => 'monthly', 'bracket_from' => 20833.00, 'bracket_to' => 33332.00, 'base_tax' => 0.00, 'excess_percent' => 15.00],
            (object) ['pay_frequency' => 'monthly', 'bracket_from' => 33333.00, 'bracket_to' => 66666.00, 'base_tax' => 1875.00, 'excess_percent' => 20.00],
        ]);

        $calc = new PayrollCalculator();
        $ref = new ReflectionClass($calc);
        $m = $ref->getMethod('computeWithholdingTax');
        $m->setAccessible(true);

        // When timing=monthly, taxable passed in is per-cutoff taxable; it is annualized to monthly via periodsPerMonth().
        // For semi-monthly, periodsPerMonth=2 so taxable=11,700 => monthly base=23,400.
        $taxablePerCutoff = 11700.00;
        $expectedMonthlyTax = (23400.00 - 20833.00) * 0.15; // 385.05

        $cutoff1 = (float) $m->invoke($calc, $policy, $brackets, $taxablePerCutoff, '11-25');
        $cutoff2 = (float) $m->invoke($calc, $policy, $brackets, $taxablePerCutoff, '26-10');

        $this->assertEqualsWithDelta($expectedMonthlyTax, $cutoff1, 0.00001);
        $this->assertEqualsWithDelta(0.0, $cutoff2, 0.00001);
    }

    public function test_no_tin_means_no_withholding_tax(): void
    {
        $calc = new PayrollCalculator();
        $ref = new ReflectionClass($calc);
        $m = $ref->getMethod('shouldApplyWithholdingTax');
        $m->setAccessible(true);

        $emp = new Employee();
        $emp->tin = null;
        $dedPolicy = new PayrollDeductionPolicy();

        $this->assertFalse((bool) $m->invoke($calc, $emp, true, $dedPolicy));

        $emp->tin = '123-456-789-000';
        $this->assertTrue((bool) $m->invoke($calc, $emp, true, $dedPolicy));
    }

    public function test_allowance_is_excluded_from_taxable_gross_for_withholding(): void
    {
        $calc = new PayrollCalculator();
        $ref = new ReflectionClass($calc);
        $m = $ref->getMethod('taxableGrossForWithholding');
        $m->setAccessible(true);

        $this->assertEqualsWithDelta(10000.00, (float) $m->invoke($calc, 12500.00, 2500.00), 0.00001);
        $this->assertEqualsWithDelta(0.00, (float) $m->invoke($calc, 2000.00, 2500.00), 0.00001);
    }
}
