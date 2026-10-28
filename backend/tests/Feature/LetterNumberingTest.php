<?php
// Test penomoran surat anti-duplikat (PEMBANGUNAN.md 4.2): counter per desa per tahun,
// urutan naik, format dari pengaturan desa, dan nomor tidak pernah dipakai ulang.

namespace Tests\Feature;

use App\Models\Village;
use App\Services\LetterNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LetterNumberingTest extends TestCase
{
    use RefreshDatabase;

    private LetterNumberGenerator $gen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gen = app(LetterNumberGenerator::class);
    }

    public function test_numbers_are_sequential_and_unique_per_village(): void
    {
        $v = Village::factory()->create();

        $taken = [];

        for ($i = 1; $i <= 5; $i++) {
            $taken[] = $number = $this->gen->next($v, 2026, 10);
            $this->assertSame("470/00$i/DS/10/2026", $number);
        }

        $this->assertCount(5, array_unique($taken));
    }

    public function test_counter_is_isolated_per_village_and_per_year(): void
    {
        $a = Village::factory()->create();
        $b = Village::factory()->create();

        $this->assertSame('470/001/DS/10/2026', $this->gen->next($a, 2026, 10));
        $this->assertSame('470/001/DS/10/2026', $this->gen->next($b, 2026, 10), 'Desa lain harus mulai dari 001.');
        $this->assertSame('470/001/DS/1/2027', $this->gen->next($a, 2027, 1), 'Tahun baru memulai counter baru.');
        $this->assertSame('470/002/DS/11/2026', $this->gen->next($a, 2026, 11));
    }

    public function test_respects_custom_village_format_and_padding(): void
    {
        $v = Village::factory()->create(['letter_number_format' => '800/{seq}/KEC/{month}-{year}']);
        config(['desa.letters.number_pad' => 4]);

        $this->assertSame('800/0001/KEC/3-2026', $this->gen->next($v, 2026, 3));
        $this->assertSame('800/0002/KEC/3-2026', $this->gen->next($v, 2026, 3));
    }

    public function test_number_column_unique_constraint_is_last_guard(): void
    {
        $v = Village::factory()->create();
        $number = $this->gen->next($v);

        \App\Models\Letter::factory()->done()->create([
            'village_id' => $v->id,
            'user_id' => \App\Models\User::factory()->create(['village_id' => $v->id])->id,
            'number' => $number,
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        \App\Models\Letter::factory()->done()->create([
            'village_id' => $v->id,
            'user_id' => \App\Models\User::factory()->create(['village_id' => $v->id])->id,
            'number' => $number,
        ]);
    }
}
