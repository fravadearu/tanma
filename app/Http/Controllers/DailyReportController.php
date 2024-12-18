<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DailyReport;
use App\Models\TaskCategory;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;


class DailyReportController extends Controller
{
    public function index()
    {
        $reports = DailyReport::with(['user', 'tasks.category'])
            ->latest('report_date')
            ->paginate(15);

        return view('daily-reports.index', compact('reports'));
    }

    public function create()
    {
        $categories = TaskCategory::all();
        return view('daily-reports.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'report_date' => 'required|date',
            'tasks' => 'required|array',
            'tasks.*.category_id' => 'required|exists:task_categories,id',
            'tasks.*.date' => 'nullable|date',
            'tasks.*.batch_count' => 'nullable|integer',
            'tasks.*.claim_count' => 'nullable|integer',
            'tasks.*.start_time' => 'nullable|date_format:H:i',
            'tasks.*.end_time' => 'nullable|date_format:H:i',
            'tasks.*.sheet_count' => 'nullable|integer',
            'tasks.*.email' => 'nullable|integer',
            'tasks.*.form' => 'nullable|integer',
        ]);

        $report = DailyReport::create([
            'user_id' => Auth::id(),
            'report_date' => $validatedData['report_date']
        ]);

        $report->tasks()->createMany(
            collect($validatedData['tasks'])->map(function ($task) {
                return [
                    'task_category_id' => $task['category_id'],
                    'task_date' => $task['date'] ?? null,
                    'batch_count' => $task['batch_count'] ?? null,
                    'claim_count' => $task['claim_count'] ?? null,
                    'start_time' => $task['start_time'] ?? null,
                    'end_time' => $task['end_time'] ?? null,
                    'sheet_count' => $task['sheet_count'] ?? null,
                    'email' => $task['email'] ?? null,
                    'form' => $task['form'] ?? null,
                ];
            })
        );

        return redirect()->route('daily-reports.index')
            ->with('success', 'Daily report created successfully');
    }

    public function show(DailyReport $dailyReport)
    {
        $dailyReport->load(['user', 'tasks.category']);
        return view('daily-reports.show', compact('dailyReport'));
    }

    public function edit(DailyReport $dailyReport)
    {
        $categories = TaskCategory::all();
        $dailyReport->load(['tasks.category']);
        return view('daily-reports.edit', compact('dailyReport', 'categories'));
    }

    public function approve(DailyReport $dailyReport)
{
    $dailyReport->update([
        'is_approved' => true,
        'approved_at' => now(),
        'approved_by' => Auth::id(),
    ]);

    return redirect()->route('daily-reports.index');
}
    public function update(Request $request, DailyReport $dailyReport)
    {
        $validatedData = $request->validate([
            'report_date' => 'required|date',
            'tasks' => 'required|array',
            'tasks.*.category_id' => 'required|exists:task_categories,id',
            'tasks.*.date' => 'nullable|date',
            'tasks.*.batch_count' => 'nullable|integer',
            'tasks.*.claim_count' => 'nullable|integer',
            'tasks.*.start_time' => 'nullable|date_format:H:i',
            'tasks.*.end_time' => 'nullable|date_format:H:i',
            'tasks.*.sheet_count' => 'nullable|integer',
            'email' => 'nullable|integer',
            'form' => 'nullable|integer '
        ]);

        $dailyReport->update([
            'report_date' => $validatedData['report_date']
        ]);

        // Delete existing tasks
        $dailyReport->tasks()->delete();

        // Create new tasks
        $dailyReport->tasks()->createMany(
            collect($validatedData['tasks'])->map(function ($task) {
                return [
                    'task_category_id' => $task['category_id'],
                    'task_date' => $task['date'] ?? null,
                    'batch_count' => $task['batch_count'] ?? null,
                    'claim_count' => $task['claim_count'] ?? null,
                    'start_time' => $task['start_time'] ?? null,
                    'end_time' => $task['end_time'] ?? null,
                    'sheet_count' => $task['sheet_count'] ?? null,
                    'email' => $task['email'] ?? null,
                    'form' => $task['form'] ?? null,
                ];
            })
        );

        return redirect()->route('daily-reports.index')
            ->with('success', 'Daily report updated successfully');
    }

    public function destroy(DailyReport $dailyReport)
    {
        $dailyReport->tasks()->delete();
        $dailyReport->delete();

        return redirect()->route('daily-reports.index')
            ->with('success', 'Daily report deleted successfully');
    }

    public function dashboard(Request $request)
    {
        $selectedDate = Carbon::parse($request->get('date', now()));

        $categories = TaskCategory::all();
        $comparisons = [];

        foreach ($categories as $category) {
            // Get current day total
            $currentTotal = DailyReport::whereDate('report_date', $selectedDate)
                ->with(['tasks' => function($query) use ($category) {
                    $query->where('task_category_id', $category->id);
                }])
                ->get()
                ->flatMap->tasks
                ->sum(function ($task) {
                    return ($task->claim_count ?? 0) +
                       ($task->sheet_count ?? 0) +
                       ($task->email ?? 0) +
                       ($task->form ?? 0);
                });

            // Get previous periods totals
            $previousDay = $this->getPeriodTotal($category, $selectedDate->copy()->subDay());
            $currentWeek = $this->getPeriodTotal($category, $selectedDate);
            $previousWeek = $this->getPeriodTotal($category, $selectedDate->copy()->subWeek());
            $currentMonth = $this->getPeriodTotal($category, $selectedDate);
            $previousMonth = $this->getPeriodTotal($category, $selectedDate->copy()->subMonth());

            // Calculate percentage changes
            $comparisons[$category->name] = [
                'current_total' => $currentTotal,
                'day_change' => $this->calculatePercentageChange($currentTotal, $previousDay),
                'week_change' => $this->calculatePercentageChange($currentWeek, $previousWeek),
                'month_change' => $this->calculatePercentageChange($currentMonth, $previousMonth)
            ];        }

        $chartData = [];
        foreach($comparisons as $index => $data) {
            $chartData[$index] = $this->getInitialChartData($index, 'week');
        }

        return view('dashboard', compact('comparisons', 'selectedDate', 'chartData'));
    }

    private function getInitialChartData($categoryId, $range)
    {
        $endDate = now();
        $startDate = match($range) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'three_months' => now()->subMonths(3),
            'six_months' => now()->subMonths(6),
            'year' => now()->subYear(),
            default => now()->subWeek(),
        };

        $data = DailyReport::with(['tasks' => function($query) use ($categoryId) {
            $query->where('task_category_id', $categoryId);
        }])
        ->whereBetween('report_date', [$startDate, $endDate])
        ->orderBy('report_date')
        ->get();

        $chartData = [
            'labels' => $data->pluck('report_date')->map->format('Y-m-d'),
            'datasets' => [
                [
                    'label' => 'Total Tasks',
                    'data' => $data->map(function($report) {
                        return $report->tasks->sum(function($task) {
                            return ($task->claim_count ?? 0) +
                               ($task->sheet_count ?? 0) +
                               ($task->email ?? 0) +
                               ($task->form ?? 0);
                        });
                    }),
                ],
            ],
        ];

        return $chartData;
    }
private function getPeriodTotal($category, $date)
{
    return DailyReport::whereDate('report_date', $date)
        ->with(['tasks' => function($query) use ($category) {
            $query->where('task_category_id', $category->id);
        }])
        ->get()
        ->flatMap->tasks
        ->sum(function ($task) {
            return ($task->claim_count ?? 0) +
                   ($task->sheet_count ?? 0) +
                   ($task->email ?? 0) +
                   ($task->form ?? 0);
        });
}

private function calculatePercentageChange($current, $previous)
{
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 2);
}

public function getChartData($categoryId, $range)
{
    $endDate = now();
    $startDate = match($range) {
        'week' => now()->subWeek(),
        'month' => now()->subMonth(),
        'three_months' => now()->subMonths(3),
        'six_months' => now()->subMonths(6),
        'year' => now()->subYear(),
        default => now()->subWeek(),
    };

    $data = DailyReport::with(['tasks' => function($query) use ($categoryId) {
        $query->where('task_category_id', $categoryId);
    }])
    ->whereBetween('report_date', [$startDate, $endDate])
    ->orderBy('report_date')
    ->get();

    $chartData = [
        'labels' => $data->pluck('report_date')->map->format('Y-m-d'),
        'datasets' => [
            [
                'label' => 'Total Tasks',
                'data' => $data->map(function($report) {
                    return $report->tasks->sum(function($task) {
                        return ($task->claim_count ?? 0) +
                               ($task->sheet_count ?? 0) +
                               ($task->email ?? 0) +
                               ($task->form ?? 0);
                    });
                }),
                'borderColor' => 'rgb(59, 130, 246)',
                'tension' => 0.1
            ]
        ]
    ];

    return response()->json($chartData);
}





public function exportToExcel(Request $request)
{
    $startDate = Carbon::parse($request->start_date);
    $endDate = Carbon::parse($request->end_date);
    
    $categories = TaskCategory::all();
    $spreadsheet = new Spreadsheet();
    
    foreach ($categories as $index => $category) {
        if ($index > 0) {
            $spreadsheet->createSheet();
        }
        $spreadsheet->setActiveSheetIndex($index);
        $sheet = $spreadsheet->getActiveSheet();
        
        $sheetName = preg_replace('/[\[\]\*\/\\\?:]/', '', $category->name);
        $sheetName = substr($sheetName, 0, 31);
        
        $sheet->setTitle($sheetName);
        
        // Set headers
        $sheet->setCellValue('A1', 'Date');
        $sheet->setCellValue('B1', 'User');
        $sheet->setCellValue('C1', 'Batch Count');
        $sheet->setCellValue('D1', 'Claim Count');
        $sheet->setCellValue('E1', 'Sheet Count');
        $sheet->setCellValue('F1', 'Email');
        $sheet->setCellValue('G1', 'Form');
        $sheet->setCellValue('H1', 'Start Time');
        $sheet->setCellValue('I1', 'End Time');
        
        $reports = DailyReport::with(['user', 'tasks' => function($query) use ($category) {
            $query->where('task_category_id', $category->id);
        }])
        ->whereBetween('report_date', [$startDate, $endDate])
        ->orderBy('report_date')
        ->get();
        
        $row = 2;
        foreach ($reports as $report) {
            foreach ($report->tasks as $task) {
                $sheet->setCellValue('A' . $row, $report->report_date);
                $sheet->setCellValue('B' . $row, $report->user->name);
                $sheet->setCellValue('C' . $row, $task->batch_count ?? 0);
                $sheet->setCellValue('D' . $row, $task->claim_count ?? 0);
                $sheet->setCellValue('E' . $row, $task->sheet_count ?? 0);
                $sheet->setCellValue('F' . $row, $task->email ?? 0);
                $sheet->setCellValue('G' . $row, $task->form ?? 0);
                $sheet->setCellValue('H' . $row, $task->start_time);
                $sheet->setCellValue('I' . $row, $task->end_time);
                $row++;
            }
        }
        
        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
    
    $writer = new Xlsx($spreadsheet);
    $filename = 'daily_reports_' . now()->format('Y-m-d') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
}}