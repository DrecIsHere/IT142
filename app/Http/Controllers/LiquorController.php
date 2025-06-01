<?php

namespace App\Http\Controllers;

use App\Services\GoogleSheetsService;
use Illuminate\Http\Request; // Make sure this use statement is present
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LiquorController extends Controller
{
    protected $sheetsService;
    protected string $sheetName = 'Inventory'; // Make sure this matches your sheet name
    // Define the headers you expect in your Google Sheet. The order matters.
    protected array $expectedHeaders = ['ID', 'Name', 'Type', 'Brand', 'Volume (ml)', 'Price', 'Quantity', 'Last Updated'];

    public function __construct(GoogleSheetsService $sheetsService)
    {
        $this->sheetsService = $sheetsService;
    }

    /**
     * Display a listing of the resource.
     * MODIFIED TO INCLUDE SEARCH FUNCTIONALITY
     */
    public function index(Request $request) // Added Request $request parameter
    {
        $range = $this->sheetName . '!A:' . $this->getColumnLetter(count($this->expectedHeaders));
        $rawDataFromSheet = $this->sheetsService->getSheetData($range);

        $liquorItems = [];
        $displayHeaders = $this->expectedHeaders; // Use expectedHeaders for display and data mapping

        if (!empty($rawDataFromSheet)) {
            $actualSheetHeaders = array_shift($rawDataFromSheet); // Get actual headers from sheet

            if ($actualSheetHeaders !== $this->expectedHeaders) {
                Log::warning('Google Sheet headers do not perfectly match expected headers from controller.', [
                    'sheet_headers'    => $actualSheetHeaders,
                    'expected_headers' => $this->expectedHeaders,
                ]);
                // We will still attempt to map data assuming column order matches expectedHeaders
            }

            foreach ($rawDataFromSheet as $rowIndex => $row) { // $rawDataFromSheet now contains only data rows
                if (empty(array_filter($row, fn($value) => !is_null($value) && $value !== ''))) { // Skip entirely blank-like rows
                    continue;
                }

                $rowCount = count($row);
                $expectedHeaderCount = count($this->expectedHeaders);

                if ($rowCount < $expectedHeaderCount) {
                    $row = array_pad($row, $expectedHeaderCount, null);
                } elseif ($rowCount > $expectedHeaderCount) {
                    $row = array_slice($row, 0, $expectedHeaderCount);
                }
                
                $liquorItems[] = array_combine($this->expectedHeaders, $row);
            }
        }

        // ****** ADDED SEARCH FILTERING LOGIC ******
        $searchTerm = $request->input('search'); // Get search term from the request

        if ($searchTerm) {
            $searchTerm = strtolower($searchTerm); // Convert search term to lowercase for case-insensitive search
            $liquorItems = array_filter($liquorItems, function ($item) use ($searchTerm) {
                // Check if search term exists in 'Name' or 'Type'
                // Ensure 'Name' and 'Type' keys exist and their values are strings
                $name = isset($item['Name']) && is_string($item['Name']) ? strtolower($item['Name']) : '';
                $type = isset($item['Type']) && is_string($item['Type']) ? strtolower($item['Type']) : '';

                // Use str_contains (PHP 8+) or stripos for older PHP versions
                // str_contains is case-sensitive by default, but we've lowercased both haystack and needle
                return str_contains($name, $searchTerm) || str_contains($type, $searchTerm);
            });
        }
        // ****** END OF SEARCH FILTERING LOGIC ******

        // The search term is available in the view via request('search') for repopulating the input box
        return view('liquor.index', ['liquorItems' => $liquorItems, 'headers' => $displayHeaders]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $formFields = $this->expectedHeaders;
        $formFields = array_filter($formFields, fn($field) => $field !== 'ID' && $field !== 'Last Updated');
        return view('liquor.create', compact('formFields'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validationRules = [];
        foreach ($this->expectedHeaders as $header) {
            if ($header !== 'ID' && $header !== 'Last Updated') {
                $requestKey = str_replace([' ', '(', ')'], '_', strtolower($header));
                $validationRules[$requestKey] = 'required|string|max:255';
                if ($header === 'Price' || $header === 'Volume (ml)') {
                    $validationRules[$requestKey] = 'required|numeric';
                }
                if ($header === 'Quantity') {
                    $validationRules[$requestKey] = 'required|integer';
                }
            }
        }
        $request->validate($validationRules);

        $newRow = [];
        foreach ($this->expectedHeaders as $header) {
            if ($header === 'ID') {
                $newRow[] = (string) Str::uuid();
            } elseif ($header === 'Last Updated') {
                $newRow[] = now()->toDateTimeString();
            } else {
                $requestKey = str_replace([' ', '(', ')'], '_', strtolower($header));
                $newRow[] = $request->input($requestKey, '');
            }
        }

        $values = [$newRow];
        $range = $this->sheetName;
        $response = $this->sheetsService->appendSheetData($range, $values);

        if ($response && $response->getUpdates()->getUpdatedCells() > 0) {
            return redirect()->route('liquor.index')->with('success', 'Liquor item added successfully with ID.');
        } else {
            Log::error('Failed to append data to Google Sheet or no cells were updated.');
            return redirect()->back()->with('error', 'Failed to add liquor item. Please check logs.')->withInput();
        }
    }

    /**
     * Helper function to find a row (and its index) by its Unique ID.
     */
    private function findRowById(string $id): ?array
    {
        $idColumnIndex = array_search('ID', $this->expectedHeaders);
        if ($idColumnIndex === false) {
            Log::error('Configuration error: "ID" header not found in expectedHeaders. Cannot find row by ID.');
            return null;
        }

        $range = $this->sheetName . '!A:' . $this->getColumnLetter(count($this->expectedHeaders));
        $rawDataFromSheet = $this->sheetsService->getSheetData($range);

        if (!empty($rawDataFromSheet)) {
            $sheetHeaders = array_shift($rawDataFromSheet); 

            foreach ($rawDataFromSheet as $index => $row) {
                if (!isset($row[$idColumnIndex])) {
                    continue; 
                }
                if ($row[$idColumnIndex] === $id) {
                    $rowCount = count($row);
                    $expectedHeaderCount = count($this->expectedHeaders);
                    if ($rowCount < $expectedHeaderCount) {
                        $row = array_pad($row, $expectedHeaderCount, null);
                    } elseif ($rowCount > $expectedHeaderCount) {
                        $row = array_slice($row, 0, $expectedHeaderCount);
                    }
                    $itemData = array_combine($this->expectedHeaders, $row);
                    return ['rowIndex' => $index, 'rowData' => $itemData];
                }
            }
        }
        return null;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $itemDetails = $this->findRowById($id);

        if (!$itemDetails) {
            Log::error("Item with ID {$id} not found for editing.");
            return redirect()->route('liquor.index')->with('error', 'Liquor item not found.');
        }

        $item = $itemDetails['rowData'];
        $sheetRowNumber = $itemDetails['rowIndex'] + 2; 
        $formFields = $this->expectedHeaders;
        $formFields = array_filter($formFields, fn($field) => $field !== 'ID' && $field !== 'Last Updated');

        return view('liquor.edit', compact('item', 'formFields', 'id', 'sheetRowNumber'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validationRules = [];
        foreach ($this->expectedHeaders as $header) {
            if ($header !== 'ID' && $header !== 'Last Updated') {
                $requestKey = str_replace([' ', '(', ')'], '_', strtolower($header));
                $validationRules[$requestKey] = 'nullable|string|max:255';
                if ($header === 'Name') {
                     $validationRules[$requestKey] = 'required|string|max:255';
                }
                if ($header === 'Price' || $header === 'Volume (ml)') {
                    $validationRules[$requestKey] = 'nullable|numeric';
                     if ($header === 'Price') $validationRules[$requestKey] = 'required|numeric';
                }
                if ($header === 'Quantity') {
                    $validationRules[$requestKey] = 'nullable|integer';
                     $validationRules[$requestKey] = 'required|integer';
                }
            }
        }
        $request->validate($validationRules);

        $itemDetails = $this->findRowById($id);
        if (!$itemDetails) {
            Log::error("Item with ID {$id} not found for updating.");
            return redirect()->route('liquor.index')->with('error', 'Liquor item not found for update.');
        }
        $sheetRowNumber = $itemDetails['rowIndex'] + 2;

        $updatedRowValues = [];
        foreach ($this->expectedHeaders as $header) {
            if ($header === 'ID') {
                $updatedRowValues[] = $id;
            } elseif ($header === 'Last Updated') {
                $updatedRowValues[] = now()->toDateTimeString();
            } else {
                $requestKey = str_replace([' ', '(', ')'], '_', strtolower($header));
                $updatedRowValues[] = $request->input($requestKey, $itemDetails['rowData'][$header] ?? '');
            }
        }

        $updateRange = $this->sheetName . '!A' . $sheetRowNumber . ':' . $this->getColumnLetter(count($this->expectedHeaders)) . $sheetRowNumber;
        $response = $this->sheetsService->updateSheetData($updateRange, [$updatedRowValues]);

        if ($response && $response->getUpdatedCells() > 0) {
            return redirect()->route('liquor.index')->with('success', 'Liquor item updated successfully.');
        } else {
            Log::error('Failed to update data in Google Sheet or no cells were updated for ID: ' . $id, [
                'range' => $updateRange,
                'response' => $response ? json_encode($response->toPrimitive()) : 'No response object or update failed'
            ]);
            return redirect()->back()->with('error', 'Failed to update liquor item. Please check logs.')->withInput();
        }
    }
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id) // $id is the unique ID
{
    $itemDetails = $this->findRowById($id);

    if (!$itemDetails) {
        Log::error("Item with ID {$id} not found for deletion.");
        return redirect()->route('liquor.index')->with('error', 'Liquor item not found to delete.');
    }

    // $itemDetails['rowIndex'] is the 0-based index of the data row 
    // (i.e., 0 is the first data row *after* the sheet's header row).
    $dataRowIndexFound = $itemDetails['rowIndex']; 

    // The Google Sheets API's deleteDimension startIndex is 0-based for the physical sheet.
    // If headers are in row 1 (API index 0), the first data row is row 2 (API index 1).
    // So, the physical row index to delete is $dataRowIndexFound + 1.
    $physicalRowIndexToDelete = $dataRowIndexFound + 1; 

    $sheetGid = env('GOOGLE_SHEET_GID');
    if (is_null($sheetGid) || !is_numeric($sheetGid)) {
        Log::error('GOOGLE_SHEET_GID is not configured or invalid in .env file. Cannot delete row.');
        return redirect()->route('liquor.index')->with('error', 'Sheet configuration error. Cannot delete item.');
    }
    $sheetGid = (int)$sheetGid;

    // Pass the corrected physicalRowIndexToDelete to the service method
    $response = $this->sheetsService->deleteSheetDimension($sheetGid, $physicalRowIndexToDelete);

    if ($response && is_iterable($response->getReplies()) && isset($response->getReplies()[0])) {
        if (is_object($response->getReplies()[0]) && empty((array)$response->getReplies()[0])) {
             return redirect()->route('liquor.index')->with('success', 'Liquor item deleted successfully.');
        } else {
            Log::warning('Delete operation reply was not the expected empty object for ID: ' . $id, [
                'response_replies' => json_encode($response->getReplies())
            ]);
            // Even if reply is unexpected, the delete might have occurred.
            return redirect()->route('liquor.index')->with('success', 'Liquor item deleted. Please verify.');
        }
    } else {
        Log::error('Failed to delete item from Google Sheet for ID: ' . $id . '. Response was null or problematic.', [
            'physicalRowIndexAttempted' => $physicalRowIndexToDelete, // Log the index used
            'response' => $response ? json_encode($response->toPrimitive()) : 'Response was null'
        ]);
        return redirect()->route('liquor.index')->with('error', 'Failed to delete liquor item. Please check logs.');
    }
}
    private function getColumnLetter(int $columnNumber): string
    {
        $letter = '';
        while ($columnNumber > 0) {
            $temp = ($columnNumber - 1) % 26;
            $letter = chr($temp + 65) . $letter;
            $columnNumber = ($columnNumber - $temp - 1) / 26;
        }
        return $letter ?: 'A';
    }
}