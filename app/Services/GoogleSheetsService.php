<?php

namespace App\Services;

use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_ValueRange;
use Illuminate\Support\Facades\Log; // For logging errors

class GoogleSheetsService
{
    protected $client;
    protected $service;
    protected string $spreadsheetId;
    protected string $credentialsPath;

    public function __construct()
    {
        $this->spreadsheetId = env('GOOGLE_SPREADSHEET_ID');
        $this->credentialsPath = base_path(env('GOOGLE_APPLICATION_CREDENTIALS')); // Use base_path for absolute path

        if (empty($this->spreadsheetId)) {
            Log::error('Google Spreadsheet ID is not configured in .env file.');
            throw new \Exception('Google Spreadsheet ID is not configured.');
        }

        if (!file_exists($this->credentialsPath)) {
            Log::error('Google application credentials file not found at: ' . $this->credentialsPath);
            throw new \Exception('Google application credentials file not found.');
        }

        $this->client = new Google_Client();
        try {
            $this->client->setAuthConfig($this->credentialsPath);
            $this->client->addScope(Google_Service_Sheets::SPREADSHEETS); // Scope for reading and writing
            // $this->client->setAccessType('offline'); // Usually not needed for service accounts as they are always "offline"

            $this->service = new Google_Service_Sheets($this->client);
        } catch (\Google\Exception $e) {
            Log::error('Google API Client Exception: ' . $e->getMessage());
            throw new \Exception('Failed to initialize Google Sheets Service: ' . $e->getMessage());
        }
    }

    /**
     * Get data from a specific sheet and range.
     * Example range: 'Sheet1!A:D' (gets all data from columns A to D in Sheet1)
     * Example range: 'Sheet1' (gets all data from Sheet1)
     *
     * @param string $range
     * @return array Returns an array of rows, or an empty array on failure or if no data.
     */
    public function getSheetData(string $range): array
    {
        try {
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
            return $response->getValues() ?? []; // Return values or empty array if null
        } catch (\Exception $e) {
            Log::error('Error getting sheet data: ' . $e->getMessage(), [
                'spreadsheetId' => $this->spreadsheetId,
                'range' => $range
            ]);
            return []; // Return empty array on error
        }
    }

    /**
     * Append new rows to a sheet.
     * $values should be an array of arrays, e.g., [['Cola', '12oz', 1.50, 100]]
     *
     * @param string $range The A1 notation of a range to search for a logical table of data.
     * Values will be appended after the last row of the table. e.g., 'Inventory!A1' or just 'Inventory'
     * @param array $values An array of arrays, where each inner array represents a row.
     * @return \Google_Service_Sheets_AppendValuesResponse|null
     */
    public function appendSheetData(string $range, array $values): ?\Google_Service_Sheets_AppendValuesResponse
    {
        try {
            $body = new Google_Service_Sheets_ValueRange([
                'values' => $values
            ]);
            $params = [
                'valueInputOption' => 'USER_ENTERED' // Or 'RAW'. USER_ENTERED means Google Sheets will interpret values (e.g., "1/1/2025" as a date).
            ];
            return $this->service->spreadsheets_values->append($this->spreadsheetId, $range, $body, $params);
        } catch (\Exception $e) {
            Log::error('Error appending sheet data: ' . $e->getMessage(), [
                'spreadsheetId' => $this->spreadsheetId,
                'range' => $range,
                'values' => $values // Be careful logging potentially sensitive data
            ]);
            return null;
        }
    }

    /**
     * Update existing cells in a sheet.
     * $values should be an array of arrays, e.g., [['Updated Item', 'New Desc', 25]]
     *
     * @param string $range The A1 notation of the values to update. e.g., 'Inventory!A5:C5'
     * @param array $values An array of arrays, where each inner array represents a row.
     * @return \Google_Service_Sheets_UpdateValuesResponse|null
     */
    public function updateSheetData(string $range, array $values): ?\Google_Service_Sheets_UpdateValuesResponse
    {
        try {
            $body = new Google_Service_Sheets_ValueRange([
                'values' => $values
            ]);
            $params = [
                'valueInputOption' => 'USER_ENTERED'
            ];
            return $this->service->spreadsheets_values->update($this->spreadsheetId, $range, $body, $params);
        } catch (\Exception $e) {
            Log::error('Error updating sheet data: ' . $e->getMessage(), [
                'spreadsheetId' => $this->spreadsheetId,
                'range' => $range
            ]);
            return null;
        }
    }

    /**
     * Clears values from a spreadsheet.
     * The request clears all values from the range.
     *
     * @param string $range The A1 notation of the range to clear. e.g., 'Inventory!A5:C5'
     * @return \Google_Service_Sheets_ClearValuesResponse|null
     */
    public function clearSheetData(string $range): ?\Google_Service_Sheets_ClearValuesResponse
    {
        try {
            $body = new \Google_Service_Sheets_ClearValuesRequest(); // Body is empty for clear
            return $this->service->spreadsheets_values->clear($this->spreadsheetId, $range, $body);
        } catch (\Exception $e) {
            Log::error('Error clearing sheet data: ' . $e->getMessage(), [
                'spreadsheetId' => $this->spreadsheetId,
                'range' => $range
            ]);
            return null;
        }
    }
    public function deleteSheetDimension(int $sheetGid, int $rowIndexToDelete): ?\Google_Service_Sheets_BatchUpdateSpreadsheetResponse
{
    try {
        $requests = [
            new \Google_Service_Sheets_Request([
                'deleteDimension' => [
                    'range' => [
                        'sheetId'   => $sheetGid,
                        'dimension' => 'ROWS',
                        'startIndex'=> $rowIndexToDelete, // 0-based index
                        'endIndex'  => $rowIndexToDelete + 1 // Deletes one row
                    ]
                ]
            ])
        ];

        $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => $requests
        ]);

        return $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $batchUpdateRequest);
    } catch (\Exception $e) {
        Log::error('Error deleting sheet dimension (row): ' . $e->getMessage(), [
            'spreadsheetId' => $this->spreadsheetId,
            'sheetGid' => $sheetGid,
            'rowIndexToDelete' => $rowIndexToDelete,
            'exception' => $e
        ]);
        return null;
    }
}
}