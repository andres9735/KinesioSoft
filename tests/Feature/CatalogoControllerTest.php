<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Http\Middleware\EnsureUserProfileIsComplete;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CatalogoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (RoleEnum::cases() as $rol) {
            Role::findOrCreate($rol->value, 'web');
        }
    }

    public function test_admin_can_view_catalog_hub(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.index'))
            ->assertOk()
            ->assertViewIs('catalogos.index')
            ->assertSeeText('Catálogos');
    }

    public function test_superadmin_can_view_catalog_hub(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::SUPERADMIN))
            ->get(route('catalogos.index'))
            ->assertOk()
            ->assertViewIs('catalogos.index');
    }

    public function test_paciente_cannot_view_catalog_hub(): void
    {
        $this->withoutMiddleware(EnsureUserProfileIsComplete::class);

        $this->actingAs($this->userWithRole(RoleEnum::PACIENTE))
            ->get(route('catalogos.index'))
            ->assertForbidden();
    }

    public function test_kinesiologo_cannot_view_catalog_hub(): void
    {
        $this->withoutMiddleware(EnsureUserProfileIsComplete::class);

        $this->actingAs($this->userWithRole(RoleEnum::KINESIOLOGO))
            ->get(route('catalogos.index'))
            ->assertForbidden();
    }

    public function test_hub_shows_both_groups_and_ten_definitive_names(): void
    {
        $xpath = $this->hubXPath();

        $groups = [
            'catalogos-profesionales' => [
                'Profesionales',
                'Jurisdicciones',
                'Colegios emisores',
                'Títulos profesionales',
                'Especialidades de kinesiología',
            ],
            'catalogos-clinicos' => [
                'Clínicos',
                'Diagnósticos clínicos',
                'Pruebas funcionales',
                'Técnicas terapéuticas',
                'Categorías clínicas',
                'Ejercicios domiciliarios',
                'Tipos de equipo terapéutico',
            ],
        ];

        foreach ($groups as $id => $catalogNames) {
            $groupName = array_shift($catalogNames);
            $section = $xpath->query("//nav[@aria-label='Catálogos maestros']/section[@aria-labelledby='$id']");
            $this->assertCount(1, $section);
            $this->assertSame($groupName, trim($xpath->query(".//h3[@id='$id']", $section->item(0))->item(0)->textContent));

            $cards = $xpath->query('.//h4', $section->item(0));
            $this->assertCount(count($catalogNames), $cards);

            foreach ($catalogNames as $index => $name) {
                $this->assertSame($name, trim($cards->item($index)->textContent));
            }
        }
    }

    public function test_professional_cards_link_to_the_four_real_routes(): void
    {
        $xpath = $this->hubXPath();
        $links = $xpath->query("//section[@aria-labelledby='catalogos-profesionales']//a");
        $this->assertCount(4, $links);

        $expectedRoutes = [
            'Jurisdicciones' => 'catalogos.jurisdicciones.index',
            'Colegios emisores' => 'catalogos.colegios.index',
            'Títulos profesionales' => 'catalogos.titulos.index',
            'Especialidades de kinesiología' => 'catalogos.especialidades.index',
        ];

        foreach ($links as $link) {
            $name = trim($xpath->query('./h4', $link)->item(0)->textContent);
            $this->assertArrayHasKey($name, $expectedRoutes);
            $this->assertSame(route($expectedRoutes[$name]), $link->getAttribute('href'));
        }
    }

    public function test_clinical_cards_link_to_the_six_real_routes(): void
    {
        $xpath = $this->hubXPath();
        $links = $xpath->query("//section[@aria-labelledby='catalogos-clinicos']//a");

        $this->assertCount(6, $links);

        $expectedRoutes = [
            'Diagnósticos clínicos' => 'catalogos.diagnosticos.index',
            'Pruebas funcionales' => 'catalogos.pruebas.index',
            'Técnicas terapéuticas' => 'catalogos.tecnicas.index',
            'Categorías clínicas' => 'catalogos.categorias.index',
            'Ejercicios domiciliarios' => 'catalogos.ejercicios.index',
            'Tipos de equipo terapéutico' => 'catalogos.tipos-equipo.index',
        ];

        foreach ($links as $link) {
            $name = trim($xpath->query('./h4', $link)->item(0)->textContent);
            $this->assertArrayHasKey($name, $expectedRoutes);
            $this->assertSame(route($expectedRoutes[$name]), $link->getAttribute('href'));
        }

        $this->assertCount(10, $xpath->query("//nav[@aria-label='Catálogos maestros']//a"));
        $this->assertCount(0, $xpath->query("//nav[@aria-label='Catálogos maestros']//a[@href='#' or starts-with(@href, 'javascript:')]"));
    }

    private function hubXPath(): DOMXPath
    {
        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMINISTRADOR))
            ->get(route('catalogos.index'));

        $response->assertOk();

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function userWithRole(RoleEnum $rol): User
    {
        $user = User::factory()->create(['activo' => true]);
        $user->assignRole($rol->value);

        return $user;
    }
}
