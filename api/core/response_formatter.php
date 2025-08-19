<?php
/**
 * ARMIS API Response Formatter
 * Provides consistent response formatting across all API endpoints
 */

class ResponseFormatter {
    
    /**
     * Format API response with consistent structure
     */
    public function format($data, $statusCode = 200) {
        $response = [
            'success' => $statusCode >= 200 && $statusCode < 300,
            'status_code' => $statusCode,
            'timestamp' => date('c'),
            'api_version' => ARMIS_API_VERSION
        ];
        
        if ($response['success']) {
            $response['data'] = $data;
        } else {
            $response['error'] = $data;
        }
        
        // Add pagination info if present
        if (isset($data['pagination'])) {
            $response['pagination'] = $data['pagination'];
            unset($response['data']['pagination']);
        }
        
        // Add metadata if present
        if (isset($data['metadata'])) {
            $response['metadata'] = $data['metadata'];
            unset($response['data']['metadata']);
        }
        
        return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
    /**
     * Format error response
     */
    public function formatError($message, $code = 400, $details = null) {
        $error = [
            'message' => $message,
            'code' => $code,
            'timestamp' => date('c')
        ];
        
        if ($details !== null) {
            $error['details'] = $details;
        }
        
        return $this->format($error, $code);
    }
    
    /**
     * Format success response with optional message
     */
    public function formatSuccess($data, $message = null, $statusCode = 200) {
        $response = $data;
        
        if ($message !== null) {
            $response = [
                'message' => $message,
                'data' => $data
            ];
        }
        
        return $this->format($response, $statusCode);
    }
    
    /**
     * Format paginated response
     */
    public function formatPaginated($data, $page, $limit, $total, $message = null) {
        $response = [
            'data' => $data,
            'pagination' => [
                'current_page' => (int)$page,
                'per_page' => (int)$limit,
                'total_items' => (int)$total,
                'total_pages' => (int)ceil($total / $limit),
                'has_next' => $page < ceil($total / $limit),
                'has_previous' => $page > 1
            ]
        ];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        return $this->format($response, 200);
    }
}
?>