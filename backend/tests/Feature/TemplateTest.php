<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\TemplateRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Create user and authenticate
        $this->user = User::factory()->create();
    }

    /**
     * Test TemplateRendererService variable replacements and detail tables.
     */
    public function test_template_renderer_service()
    {
        $renderer = new TemplateRendererService();

        $html = '<div class="test">Tên khách sạn: {{hotel.name}} - Khách hàng: {{customer.name}}</div>';
        $css = '.test { color: red; }';
        
        $data = [
            'hotel' => ['name' => 'Galliot Hotel'],
            'customer' => ['name' => 'Nguyen Van A']
        ];

        $result = $renderer->render($html, $css, $data);

        $this->assertStringContainsString('Tên khách sạn: Galliot Hotel', $result);
        $this->assertStringContainsString('Khách hàng: Nguyen Van A', $result);
        $this->assertStringContainsString('.test { color: red; }', $result);
    }

    /**
     * Test TemplateRendererService detail row lặp dữ liệu (Banded Detail Band).
     */
    public function test_template_renderer_detail_loop()
    {
        $renderer = new TemplateRendererService();

        $html = '<table>
            <tr class="pms-detail-row" data-source="booking.services">
                <td>{{service.name}}</td>
                <td>{{service.price}}</td>
            </tr>
        </table>';

        $data = [
            'booking' => [
                'services' => [
                    ['name' => 'Ăn sáng', 'price' => '100,000'],
                    ['name' => 'Giặt là', 'price' => '50,000']
                ]
            ]
        ];

        $result = $renderer->render($html, '', $data);

        $this->assertStringContainsString('<td>Ăn sáng</td>', $result);
        $this->assertStringContainsString('<td>100,000</td>', $result);
        $this->assertStringContainsString('<td>Giặt là</td>', $result);
        $this->assertStringContainsString('<td>50,000</td>', $result);
    }

    /**
     * Test templates CRUD and api endpoints.
     */
    public function test_template_controller_crud()
    {
        $this->actingAs($this->user);

        // 1. Create Template
        $payload = [
            'group' => 'Booking Confirmation',
            'name' => 'Xác nhận Galliot',
            'report' => 'ReportGalliot',
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 12,
            'margin_bottom' => 12,
            'margin_left' => 12,
            'margin_right' => 12,
            'content_json' => [
                'header' => [['id' => 'h1', 'type' => 'text', 'content' => '<h1>Galliot Hotel</h1>', 'style' => []]],
                'detail' => [],
                'footer' => []
            ],
            'content_html' => '<div class="report-header"><h1>Galliot Hotel</h1></div>',
            'css' => 'h1 { font-weight: bold; }'
        ];

        $response = $this->postJson('/api/templates', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Xác nhận Galliot');
        $response->assertJsonPath('data.is_default', true); // First in group is default

        $templateId = $response->json('data.id');

        // 2. Fetch Details
        $response = $this->getJson("/api/templates/{$templateId}");
        $response->assertStatus(200);
        $response->assertJsonPath('data.page_size', 'A4');

        // 3. Update & Version Trigger
        $updatePayload = array_merge($payload, [
            'name' => 'Xác nhận Galliot V2',
            'content_html' => '<div class="report-header"><h1>Galliot Hotel V2</h1></div>',
            'note' => 'Cập nhật tiêu đề Galliot'
        ]);

        $response = $this->putJson("/api/templates/{$templateId}", $updatePayload);
        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Xác nhận Galliot V2');
        $response->assertJsonPath('data.version', '1.1'); // Version incremented

        // Check if version entries are created
        $this->assertEquals(2, TemplateVersion::where('template_id', $templateId)->count());

        // 4. Duplicate
        $response = $this->postJson("/api/templates/{$templateId}/duplicate");
        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Sao chép mẫu biểu thành công');
        $this->assertDatabaseHas('templates', [
            'name' => 'Xác nhận Galliot V2 - Sao chép',
            'is_default' => false
        ]);

        $duplicatedId = $response->json('data.id');

        // 5. Make Default
        $response = $this->postJson("/api/templates/{$duplicatedId}/make-default");
        $response->assertStatus(200);
        
        $this->assertTrue(Template::find($duplicatedId)->is_default);
        $this->assertFalse(Template::find($templateId)->is_default); // Original became false

        // 6. Rollback
        $firstVersion = TemplateVersion::where('template_id', $templateId)->orderBy('created_at', 'asc')->first();
        $response = $this->postJson("/api/templates/{$templateId}/rollback", [
            'version_id' => $firstVersion->id
        ]);
        $response->assertStatus(200);
        $this->assertEquals('2.1', Template::find($templateId)->version); // Rollback major version change
        $this->assertStringContainsString('Galliot Hotel', Template::find($templateId)->content_html); // Restored HTML

        // 7. Preview HTML
        $response = $this->getJson("/api/templates/{$templateId}/preview");
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'html']);
    }

    /**
     * Test template image upload.
     */
    public function test_template_image_upload()
    {
        $this->actingAs($this->user);

        // Mock upload file
        $file = \Illuminate\Http\UploadedFile::fake()->image('test_logo.png', 100, 100);

        $response = $this->postJson('/api/templates/upload-image', [
            'image' => $file
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        
        $url = $response->json('url');
        $this->assertStringContainsString('/uploads/templates/template_', $url);

        $fullPath = public_path($url);
        $this->assertFileExists($fullPath);

        // Cleanup
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
}

