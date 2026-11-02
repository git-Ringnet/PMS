<?php

namespace App\Services\Reports;

use App\Models\ReportDefinition;
use App\Models\ReportMenuNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dòng 327: cấu hình menu báo cáo theo từng module.
 * - Báo cáo có hiện ở module hay không vẫn lưu ở report_definitions.menu_locations (báo cáo thêm bằng migration vẫn tự hiện).
 * - Nhóm, thứ tự nhóm và thứ tự báo cáo trong nhóm lưu ở report_menu_nodes.
 * - Báo cáo thuộc module nhưng chưa được xếp vào cây thì tự thêm vào cuối nhóm cùng tên (report.group).
 */
class ReportMenuService
{
    public const LOCATIONS = [
        'reservation' => 'Đăng ký phòng',
        'frontdesk' => 'Lễ tân',
        'housekeeping' => 'Buồng phòng',
    ];

    public static function belongsTo(ReportDefinition $report, string $location): bool
    {
        return $report->show_in_menu !== false
            && in_array($location, $report->menu_locations ?? ['reservation'], true);
    }

    /**
     * Cây menu của một module, hỗ trợ danh mục và báo cáo ở mọi cấp.
     *
     * @param  Collection<int, ReportDefinition>  $reports  các báo cáo được xét (đã lọc trạng thái / quyền nếu cần)
     */
    public function tree(string $location, Collection $reports): array
    {
        $members = $reports->filter(fn (ReportDefinition $report) => self::belongsTo($report, $location))->keyBy('id');
        $nodes = ReportMenuNode::where('location', $location)->orderBy('sort_order')->orderBy('id')->get();
        $placed = [];

        $reportItem = function (ReportDefinition $report) use (&$placed) {
            $placed[$report->id] = true;

            return [
                'type' => 'report',
                'report_id' => $report->id,
                'code' => $report->code,
                'name' => $report->name,
                'is_active' => (bool) $report->is_active,
            ];
        };

        $rootKey = '__root__';
        $childrenByParent = $nodes->groupBy(fn (ReportMenuNode $node) => $node->parent_id === null ? $rootKey : (string) $node->parent_id);
        $buildNodes = function ($parentId) use (&$buildNodes, $childrenByParent, $members, $reportItem) {
            $items = [];
            foreach ($childrenByParent->get($parentId, collect()) as $node) {
                if ($node->type === 'group') {
                    $items[] = ['type' => 'group', 'name' => $node->name, 'children' => $buildNodes($node->id)];
                    continue;
                }

                $report = $members->get($node->report_definition_id);
                if ($report) {
                    $items[] = $reportItem($report);
                }
            }

            return $items;
        };
        $tree = $buildNodes($rootKey);

        // Báo cáo chưa xếp: thêm vào nhóm cùng tên, chưa có nhóm thì tạo nhóm mới ở cuối (thứ tự như menu cũ)
        $unplaced = $members->reject(fn ($report) => isset($placed[$report->id]))
            ->groupBy(fn ($report) => $report->group ?: 'Báo cáo khác')
            ->sortBy(fn ($items, $name) => sprintf('%010d|%s', $items->min('menu_group_order'), mb_strtolower($name)));

        foreach ($unplaced as $groupName => $items) {
            $items = $items->sortBy(fn ($report) => sprintf('%010d|%s', $report->menu_item_order, mb_strtolower($report->name)));
            $index = collect($tree)->search(fn ($item) => $item['type'] === 'group'
                && mb_strtolower($item['name']) === mb_strtolower($groupName));
            if ($index === false) {
                $tree[] = ['type' => 'group', 'name' => $groupName, 'children' => []];
                $index = array_key_last($tree);
            }
            foreach ($items as $report) {
                $tree[$index]['children'][] = $reportItem($report);
            }
        }

        return $tree;
    }

    /** Lưu cây menu của module, đồng thời cập nhật báo cáo nào hiện ở module này. */
    public function save(string $location, array $tree): void
    {
        $reportIds = [];
        $validateItems = function (array $items) use (&$validateItems, &$reportIds): void {
            $groupNames = [];
            foreach ($items as $item) {
                if (($item['type'] ?? null) === 'group') {
                    $name = trim((string) ($item['name'] ?? ''));
                    if ($name === '') {
                        throw ValidationException::withMessages(['tree' => 'Tên danh mục báo cáo không được để trống.']);
                    }
                    if (in_array(mb_strtolower($name), $groupNames, true)) {
                        throw ValidationException::withMessages(['tree' => "Danh mục \"{$name}\" bị trùng tên cùng cấp."]);
                    }
                    $groupNames[] = mb_strtolower($name);
                    $children = $item['children'] ?? [];
                    if (! is_array($children)) {
                        throw ValidationException::withMessages(['tree' => 'Danh mục báo cáo có dữ liệu con không hợp lệ.']);
                    }
                    $validateItems($children);
                    continue;
                }

                if (($item['type'] ?? null) !== 'report' || empty($item['report_id'])) {
                    throw ValidationException::withMessages(['tree' => 'Mục báo cáo không hợp lệ.']);
                }
                $reportIds[] = (int) $item['report_id'];
            }
        };
        $validateItems($tree);

        if (count($reportIds) !== count(array_unique($reportIds))) {
            throw ValidationException::withMessages(['tree' => 'Một báo cáo chỉ được xếp một lần trong menu của module.']);
        }
        if (ReportDefinition::whereIn('id', $reportIds)->count() !== count($reportIds)) {
            throw ValidationException::withMessages(['tree' => 'Có báo cáo không còn tồn tại, vui lòng tải lại.']);
        }

        DB::transaction(function () use ($location, $tree, $reportIds) {
            ReportMenuNode::where('location', $location)->delete();

            $saveItems = function (array $items, ?int $parentId = null) use (&$saveItems, $location): void {
                foreach (array_values($items) as $order => $item) {
                    if ($item['type'] === 'group') {
                        $group = ReportMenuNode::create([
                            'location' => $location, 'parent_id' => $parentId, 'type' => 'group',
                            'name' => trim($item['name']), 'sort_order' => $order,
                        ]);
                        $saveItems($item['children'] ?? [], $group->id);
                        continue;
                    }
                    ReportMenuNode::create([
                        'location' => $location, 'parent_id' => $parentId, 'type' => 'report',
                        'report_definition_id' => (int) $item['report_id'], 'sort_order' => $order,
                    ]);
                }
            };
            $saveItems($tree);

            foreach (ReportDefinition::all() as $report) {
                // Báo cáo đang ẩn khỏi menu thì các khu vực cũ không còn hiệu lực
                $locations = $report->show_in_menu === false ? [] : ($report->menu_locations ?? ['reservation']);
                $inTree = in_array($report->id, $reportIds, true);
                $next = $inTree
                    ? array_values(array_unique([...$locations, $location]))
                    : array_values(array_diff($locations, [$location]));

                if ($next !== ($report->menu_locations ?? ['reservation']) || $report->show_in_menu !== ($next !== [])) {
                    $report->update(['menu_locations' => $next, 'show_in_menu' => $next !== []]);
                }
            }
        });
    }
}
