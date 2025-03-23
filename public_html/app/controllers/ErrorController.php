<?php
/**
 * Error Controller
 * 
 * Handles error pages
 */
class ErrorController extends BaseController {
    /**
     * Show 404 page
     * 
     * @return void
     */
    public function notFound() {
        http_response_code(404);
        $this->render('errors/404', [], 'main');
    }
    
    /**
     * Show 403 page
     * 
     * @return void
     */
    public function forbidden() {
        http_response_code(403);
        $this->render('errors/403', [], 'main');
    }
    
    /**
     * Show 500 page
     * 
     * @param string $error Error message
     * @return void
     */
    public function serverError($error = null) {
        http_response_code(500);
        $this->render('errors/500', ['error' => $error], 'main');
    }
}