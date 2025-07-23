<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InputSanitizationMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sanitize all input data
        $this->sanitizeRequest($request);
        
        // Validate for malicious patterns
        $this->validateForMaliciousPatterns($request);
        
        // Check file uploads
        $this->validateFileUploads($request);
        
        return $next($request);
    }
    
    private function sanitizeRequest(Request $request): void
    {
        // Get all input data
        $input = $request->all();
        
        // Recursively sanitize
        $sanitized = $this->sanitizeArray($input);
        
        // Replace request input
        $request->replace($sanitized);
    }
    
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->sanitizeString($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    private function sanitizeString(string $value): string
    {
        // Remove null bytes
        $value = str_replace("\0", '', $value);
        
        // Normalize line endings
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        
        // Remove control characters (except tab, newline, carriage return)
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        
        // Trim whitespace
        $value = trim($value);
        
        return $value;
    }
    
    private function validateForMaliciousPatterns(Request $request): void
    {
        $input = json_encode($request->all());
        $url = $request->fullUrl();
        $userAgent = $request->userAgent();
        
        $maliciousPatterns = [
            // XSS patterns
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe\b[^>]*>/i',
            '/<object\b[^>]*>/i',
            '/<embed\b[^>]*>/i',
            
            // SQL injection patterns (more specific)
            '/(\bunion\s+select\b|\bselect\s+.*\s+from\b)/i',
            '/(\binsert\s+into\b|\bdelete\s+from\b|\bdrop\s+table\b)/i',
            '/(\bexec\s*\(|\bexecute\s*\()/i',
            
            // Command injection patterns
            '/[;&|`$(){}[\]]/i',
            '/\b(eval|exec|system|shell_exec|passthru)\s*\(/i',
            
            // Path traversal patterns
            '/\.\.[\/\\\\]/i',
            '/\/(etc|proc|sys|dev)\//i',
            
            // LDAP injection patterns
            '/[()=*!&|]/i',
            
            // XXE patterns
            '/<!ENTITY\b/i',
            '/SYSTEM\s+["\']/i',
            
            // Template injection patterns
            '/\{\{.*\}\}/i',
            '/\$\{.*\}/i',
            
            // NoSQL injection patterns
            '/\$where\b/i',
            '/\$ne\b/i',
            '/\$gt\b/i',
            '/\$regex\b/i',
        ];
        
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $input) || 
                preg_match($pattern, $url) || 
                preg_match($pattern, $userAgent ?? '')) {
                
                $this->logMaliciousActivity($request, $pattern, [
                    'input' => substr($input, 0, 500),
                    'url' => $url,
                    'user_agent' => $userAgent,
                    'pattern' => $pattern
                ]);
                
                // Block the request
                abort(400, 'Malicious input detected');
            }
        }
    }
    
    private function validateFileUploads(Request $request): void
    {
        if (!$request->hasFile('*')) {
            return;
        }
        
        foreach ($request->allFiles() as $files) {
            if (!is_array($files)) {
                $files = [$files];
            }
            
            foreach ($files as $file) {
                if (!$file->isValid()) {
                    continue;
                }
                
                // Check file size (max 10MB)
                if ($file->getSize() > 10 * 1024 * 1024) {
                    $this->logMaliciousActivity($request, 'oversized_file', [
                        'file_size' => $file->getSize(),
                        'file_name' => $file->getClientOriginalName()
                    ]);
                    
                    abort(413, 'File too large');
                }
                
                // Check file extension
                $allowedExtensions = ['json', 'txt', 'csv'];
                $extension = strtolower($file->getClientOriginalExtension());
                
                if (!in_array($extension, $allowedExtensions)) {
                    $this->logMaliciousActivity($request, 'invalid_file_extension', [
                        'file_extension' => $extension,
                        'file_name' => $file->getClientOriginalName()
                    ]);
                    
                    abort(400, 'Invalid file type');
                }
                
                // Check MIME type
                $allowedMimeTypes = [
                    'application/json',
                    'text/plain',
                    'text/csv',
                    'application/csv'
                ];
                
                if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
                    $this->logMaliciousActivity($request, 'invalid_mime_type', [
                        'mime_type' => $file->getMimeType(),
                        'file_name' => $file->getClientOriginalName()
                    ]);
                    
                    abort(400, 'Invalid file format');
                }
                
                // Check for embedded scripts in file content
                $content = file_get_contents($file->getPathname());
                if ($this->containsMaliciousContent($content)) {
                    $this->logMaliciousActivity($request, 'malicious_file_content', [
                        'file_name' => $file->getClientOriginalName()
                    ]);
                    
                    abort(400, 'Malicious file content detected');
                }
            }
        }
    }
    
    private function containsMaliciousContent(string $content): bool
    {
        $maliciousPatterns = [
            '/<script\b/i',
            '/javascript:/i',
            '/<iframe\b/i',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            '/file_get_contents\s*\(/i',
            '/file_put_contents\s*\(/i',
            '/fopen\s*\(/i',
            '/curl_exec\s*\(/i',
        ];
        
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function logMaliciousActivity(Request $request, string $type, array $context): void
    {
        AuditLog::log(
            'malicious_input_detected',
            null,
            [],
            array_merge($context, [
                'detection_type' => $type,
                'ip_address' => $request->ip(),
                'endpoint' => $request->getPathInfo(),
                'method' => $request->method()
            ]),
            'critical',
            "Malicious input detected: {$type} from IP {$request->ip()}"
        );
        
        // Add penalty for this IP
        AdvancedRateLimitMiddleware::addPenalty($request->ip());
    }
}
