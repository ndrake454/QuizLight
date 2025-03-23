<?php
/**
 * Admin Controller
 * 
 * Handles admin dashboard and general admin functionality
 */
class AdminController extends BaseController {
    private $userModel;
    private $categoryModel;
    private $questionModel;
    
    public function __construct() {
        parent::__construct();
        
        // Ensure user is logged in and is an admin
        requireAdmin();
        
        $this->userModel = new UserModel();
        $this->categoryModel = new CategoryModel();
        $this->questionModel = new QuestionModel();
    }
    
    /**
     * Display admin dashboard
     * 
     * @return void
     */
    public function dashboard() {
        // Get statistics for the dashboard
        $totalUsers = $this->userModel->count();
        $totalCategories = $this->categoryModel->count();
        $totalQuestions = $this->questionModel->count();
        
        // Get recent quiz attempts
        $quizModel = new QuizModel();
        $recentQuizzes = $quizModel->getRecentAttempts(5);
        
        // Get top categories by usage
        $topCategories = $this->getTopCategories();
        
        // Get recent users
        $recentUsers = $this->userModel->getRecent(5);
        
        $this->render('admin/dashboard', [
            'pageTitle' => 'Admin Dashboard',
            'totalUsers' => $totalUsers,
            'totalCategories' => $totalCategories,
            'totalQuestions' => $totalQuestions,
            'recentQuizzes' => $recentQuizzes,
            'topCategories' => $topCategories,
            'recentUsers' => $recentUsers,
            'extraScripts' => ['/assets/js/admin-dashboard.js']
        ], 'admin');
    }
    
    /**
     * Get top categories by usage
     * 
     * @return array
     */
    private function getTopCategories() {
        $sql = "SELECT 
                c.id,
                c.name,
                COUNT(DISTINCT q.id) as num_questions,
                COUNT(DISTINCT qa.id) as num_answers
                FROM categories c
                LEFT JOIN questions q ON c.id = q.category_id
                LEFT JOIN quiz_answers qa ON q.id = qa.question_id
                GROUP BY c.id
                ORDER BY num_answers DESC
                LIMIT 5";
                
        $stmt = $this->userModel->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Display users management page
     * 
     * @return void
     */
    public function users() {
        // Get all users with pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;
        
        $users = $this->userModel->getAllPaginated($offset, $limit);
        $totalUsers = $this->userModel->count();
        $totalPages = ceil($totalUsers / $limit);
        
        $this->render('admin/users', [
            'pageTitle' => 'Manage Users',
            'users' => $users,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'extraScripts' => ['/assets/js/admin-users.js']
        ], 'admin');
    }
    
    /**
     * Toggle user admin status
     * 
     * @return void
     */
    public function toggleAdmin() {
        $userId = (int)($_POST['user_id'] ?? 0);
        $status = $_POST['status'] === 'true';
        
        if ($userId === $_SESSION['user_id']) {
            $this->json([
                'success' => false,
                'message' => 'You cannot change your own admin status'
            ]);
            return;
        }
        
        $result = $this->userModel->update($userId, ['is_admin' => $status ? 1 : 0]);
        
        $this->json([
            'success' => $result,
            'message' => $result ? 'User admin status updated' : 'Failed to update user admin status'
        ]);
    }
    
    /**
     * Delete a user
     * 
     * @return void
     */
    public function deleteUser() {
        $userId = (int)($_POST['user_id'] ?? 0);
        
        // Prevent self-deletion
        if ($userId === $_SESSION['user_id']) {
            $this->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ]);
            return;
        }
        
        $result = $this->userModel->delete($userId);
        
        $this->json([
            'success' => $result,
            'message' => $result ? 'User deleted successfully' : 'Failed to delete user'
        ]);
    }
    
    /**
     * Display categories management page
     * 
     * @return void
     */
    public function categories() {
        // Get all categories
        $categories = $this->categoryModel->all('name', 'ASC');
        
        $this->render('admin/categories', [
            'pageTitle' => 'Manage Categories',
            'categories' => $categories,
            'extraScripts' => ['/assets/js/admin-categories.js']
        ], 'admin');
    }
    
    /**
     * Create a new category
     * 
     * @return void
     */
    public function createCategory() {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($name)) {
            $this->json([
                'success' => false,
                'message' => 'Category name is required'
            ]);
            return;
        }
        
        // Check if category already exists
        $existingCategory = $this->categoryModel->findOneBy('name', $name);
        
        if ($existingCategory) {
            $this->json([
                'success' => false,
                'message' => 'A category with this name already exists'
            ]);
            return;
        }
        
        $categoryId = $this->categoryModel->createCategory($name, $description, $isActive);
        
        if ($categoryId) {
            $this->json([
                'success' => true,
                'message' => 'Category created successfully',
                'category' => [
                    'id' => $categoryId,
                    'name' => $name,
                    'description' => $description,
                    'is_active' => $isActive,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            $this->json([
                'success' => false,
                'message' => 'Failed to create category'
            ]);
        }
    }
    
    /**
     * Update a category
     * 
     * @return void
     */
    public function updateCategory() {
        $categoryId = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($name)) {
            $this->json([
                'success' => false,
                'message' => 'Category name is required'
            ]);
            return;
        }
        
        // Check if category exists
        $category = $this->categoryModel->find($categoryId);
        
        if (!$category) {
            $this->json([
                'success' => false,
                'message' => 'Category not found'
            ]);
            return;
        }
        
        // Check if another category already has this name
        $existingCategory = $this->categoryModel->findOneBy('name', $name);
        
        if ($existingCategory && $existingCategory['id'] != $categoryId) {
            $this->json([
                'success' => false,
                'message' => 'Another category with this name already exists'
            ]);
            return;
        }
        
        $result = $this->categoryModel->update($categoryId, [
            'name' => $name,
            'description' => $description,
            'is_active' => $isActive,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        $this->json([
            'success' => $result,
            'message' => $result ? 'Category updated successfully' : 'Failed to update category'
        ]);
    }
    
    /**
     * Toggle category active status
     * 
     * @return void
     */
    public function toggleCategory() {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        
        $result = $this->categoryModel->toggleActive($categoryId);
        
        $this->json([
            'success' => $result,
            'message' => $result ? 'Category status updated' : 'Failed to update category status'
        ]);
    }
    
    /**
     * Delete a category
     * 
     * @return void
     */
    public function deleteCategory() {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        
        // Check if category has questions
        $questions = $this->questionModel->findBy('category_id', $categoryId);
        
        if (count($questions) > 0) {
            $this->json([
                'success' => false,
                'message' => 'Cannot delete category that has questions. Please delete the questions first or move them to another category.'
            ]);
            return;
        }
        
        $result = $this->categoryModel->delete($categoryId);
        
        $this->json([
            'success' => $result,
            'message' => $result ? 'Category deleted successfully' : 'Failed to delete category'
        ]);
    }
    
    /**
     * Display questions management page
     * 
     * @return void
     */
    public function questions() {
        // Get filter parameters
        $categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        
        // Get pagination parameters
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;
        
        // Get questions with filters
        $questions = $this->questionModel->getFilteredQuestions($categoryId, $search, $offset, $limit);
        $totalQuestions = $this->questionModel->countFilteredQuestions($categoryId, $search);
        $totalPages = ceil($totalQuestions / $limit);
        
        // Get all categories for the filter dropdown
        $categories = $this->categoryModel->all('name', 'ASC');
        
        $this->render('admin/questions', [
            'pageTitle' => 'Manage Questions',
            'questions' => $questions,
            'categories' => $categories,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'categoryId' => $categoryId,
            'search' => $search,
            'extraScripts' => ['/assets/js/admin-questions.js']
        ], 'admin');
    }
    
    /**
     * Display question form (create/edit)
     * 
     * @return void
     */
    public function questionForm() {
        $questionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        // Get all categories for the dropdown
        $categories = $this->categoryModel->getAllActive();
        
        if ($questionId) {
            // Edit existing question
            $question = $this->questionModel->getWithAnswers($questionId);
            
            if (!$question) {
                setFlashMessage('Question not found', 'error');
                $this->redirect('/admin/questions');
                return;
            }
            
            $pageTitle = 'Edit Question';
        } else {
            // Create new question
            $question = null;
            $pageTitle = 'Create Question';
        }
        
        $this->render('admin/question-form', [
            'pageTitle' => $pageTitle,
            'question' => $question,
            'categories' => $categories,
            'extraScripts' => ['/assets/js/admin-question-form.js']
        ], 'admin');
    }
    
    /**
     * Save a question (create/update)
     * 
     * @return void
     */
    public function saveQuestion() {
        $questionId = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $questionText = trim($_POST['question_text'] ?? '');
        $questionType = $_POST['question_type'] ?? 'multiple_choice';
        $explanation = trim($_POST['explanation'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Validate inputs
        if (empty($questionText) || $categoryId === 0) {
            setFlashMessage('Question text and category are required', 'error');
            $this->redirect('/admin/question-form' . ($questionId ? "?id={$questionId}" : ''));
            return;
        }
        
        // Prepare question data
        $questionData = [
            'category_id' => $categoryId,
            'question_text' => $questionText,
            'question_type' => $questionType,
            'explanation' => $explanation,
            'is_active' => $isActive,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Handle answers based on question type
        if ($questionType === 'multiple_choice') {
            $answerTexts = $_POST['answer_text'] ?? [];
            $correctAnswer = $_POST['correct_answer'] ?? -1;
            
            if (count($answerTexts) < 2 || $correctAnswer === -1) {
                setFlashMessage('Multiple choice questions require at least two answers and one correct answer', 'error');
                $this->redirect('/admin/question-form' . ($questionId ? "?id={$questionId}" : ''));
                return;
            }
            
            $answers = [];
            foreach ($answerTexts as $index => $text) {
                if (trim($text) !== '') {
                    $answers[] = [
                        'text' => trim($text),
                        'is_correct' => (int)$correctAnswer === $index ? 1 : 0
                    ];
                }
            }
            
            if (count($answers) < 2) {
                setFlashMessage('Multiple choice questions require at least two answers', 'error');
                $this->redirect('/admin/question-form' . ($questionId ? "?id={$questionId}" : ''));
                return;
            }
        } else {
            // Written response
            $answerTexts = $_POST['written_answer'] ?? [];
            $primaryAnswer = $_POST['primary_answer'] ?? 0;
            
            if (empty($answerTexts) || count($answerTexts) === 0) {
                setFlashMessage('Written response questions require at least one possible answer', 'error');
                $this->redirect('/admin/question-form' . ($questionId ? "?id={$questionId}" : ''));
                return;
            }
            
            $answers = [];
            foreach ($answerTexts as $index => $text) {
                if (trim($text) !== '') {
                    $answers[] = [
                        'text' => trim($text),
                        'is_primary' => (int)$primaryAnswer === $index ? 1 : 0
                    ];
                }
            }
            
            if (count($answers) === 0) {
                setFlashMessage('Written response questions require at least one possible answer', 'error');
                $this->redirect('/admin/question-form' . ($questionId ? "?id={$questionId}" : ''));
                return;
            }
        }
        
        if ($questionId) {
            // Update existing question
            $questionData['updated_at'] = date('Y-m-d H:i:s');
            $result = $this->updateQuestionWithAnswers($questionId, $questionData, $answers, $questionType);
        } else {
            // Create new question
            $questionData['created_at'] = date('Y-m-d H:i:s');
            $result = $this->questionModel->createQuestion($questionData, $answers);
        }
        
        if ($result) {
            setFlashMessage('Question saved successfully', 'success');
            $this->redirect('/admin/questions');
        } else {
            setFlashMessage('Failed to save question', 'error');
            $this->redirect('/admin/question-form' . ($questionId ? "?id={$questionId}" : ''));
        }
    }
    
    /**
     * Update a question and its answers
     * 
     * @param int $questionId
     * @param array $questionData
     * @param array $answers
     * @param string $questionType
     * @return bool
     */
    private function updateQuestionWithAnswers($questionId, $questionData, $answers, $questionType) {
        try {
            $this->userModel->db->beginTransaction();
            
            // Update question
            $this->questionModel->update($questionId, $questionData);
            
            if ($questionType === 'multiple_choice') {
                // Delete existing answers
                $this->deleteAnswers($questionId);
                
                // Insert new answers
                $answerModel = new AnswerModel();
                foreach ($answers as $answer) {
                    $answerData = [
                        'question_id' => $questionId,
                        'answer_text' => $answer['text'],
                        'is_correct' => $answer['is_correct'],
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $answerModel->create($answerData);
                }
            } else {
                // Delete existing written answers
                $this->deleteWrittenAnswers($questionId);
                
                // Insert new written answers
                foreach ($answers as $answer) {
                    $sql = "INSERT INTO written_response_answers 
                            (question_id, answer_text, is_primary, created_at) 
                            VALUES (?, ?, ?, NOW())";
                    
                    $stmt = $this->userModel->db->prepare($sql);
                    $stmt->execute([
                        $questionId,
                        $answer['text'],
                        $answer['is_primary']
                    ]);
                }
            }
            
            $this->userModel->db->commit();
            return true;
        } catch (Exception $e) {
            $this->userModel->db->rollBack();
            return false;
        }
    }
    
    /**
     * Delete multiple choice answers for a question
     * 
     * @param int $questionId
     * @return bool
     */
    private function deleteAnswers($questionId) {
        $sql = "DELETE FROM answers WHERE question_id = ?";
        $stmt = $this->userModel->db->prepare($sql);
        return $stmt->execute([$questionId]);
    }
    
    /**
     * Delete written answers for a question
     * 
     * @param int $questionId
     * @return bool
     */
    private function deleteWrittenAnswers($questionId) {
        $sql = "DELETE FROM written_response_answers WHERE question_id = ?";
        $stmt = $this->userModel->db->prepare($sql);
        return $stmt->execute([$questionId]);
    }
    
    /**
     * Toggle question active status
     * 
     * @return void
     */
    public function toggleQuestion() {
        $questionId = (int)($_POST['question_id'] ?? 0);
        $status = $_POST['status'] === 'true';
        
        $result = $this->questionModel->update($questionId, [
            'is_active' => $status ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        $this->json([
            'success' => $result,
            'message' => $result ? 'Question status updated' : 'Failed to update question status'
        ]);
    }
    
    /**
     * Delete a question
     * 
     * @return void
     */
    public function deleteQuestion() {
        $questionId = (int)($_POST['question_id'] ?? 0);
        
        $result = $this->questionModel->delete($questionId);
        
        $this->json([
            'success' => $result,
            'message' => $result ? 'Question deleted successfully' : 'Failed to delete question'
        ]);
    }
}