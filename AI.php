<?php
// =========================================================================
// ADVANCED PHP & MySQL Backend Logic
// This script combines the frontend HTML/JS with a functional PHP backend.
// =========================================================================

// --- Database Connection (Assumes 'connect.php' exists with a $conn variable) ---
require_once 'connect.php';



// --- Helper Functions ---
function OpenCon() {
    global $conn;
    return $conn;
}

// =========================================================================
// SECURE FUNCTION USING PREPARED STATEMENTS
// This function handles all core database operations.
// =========================================================================
function secureDbAction($table_name, $operation, $form_data = [], $where_clause = '', $where_data = []) {
    $conn = OpenCon();
    $dbActionStatus = ['success' => false, 'message' => ''];
    $sql = '';
    $param_types = '';
    $params = [];

    // Build the query and parameters based on the operation
    switch ($operation) {
        case 1: // INSERT
            $columns = array_keys($form_data);
            $placeholders = array_fill(0, count($columns), '?');
            $sql = "INSERT INTO $table_name (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
            foreach ($form_data as $value) {
                if (is_int($value)) $param_types .= 'i';
                elseif (is_float($value)) $param_types .= 'd';
                else $param_types .= 's';
                $params[] = $value;
            }
            break;
        case 2: // UPDATE
            $sets = [];
            foreach ($form_data as $column => $value) {
                $sets[] = "$column = ?";
                if (is_int($value)) $param_types .= 'i';
                elseif (is_float($value)) $param_types .= 'd';
                else $param_types .= 's';
                $params[] = $value;
            }
            $sql = "UPDATE $table_name SET " . implode(', ', $sets) . " WHERE $where_clause";
            // Add WHERE clause parameters
            foreach ($where_data as $value) {
                if (is_int($value)) $param_types .= 'i';
                elseif (is_float($value)) $param_types .= 'd';
                else $param_types .= 's';
                $params[] = $value;
            }
            break;
        case 3: // DELETE
            $sql = "DELETE FROM $table_name WHERE $where_clause";
            // Add WHERE clause parameters
            foreach ($where_data as $value) {
                if (is_int($value)) $param_types .= 'i';
                elseif (is_float($value)) $param_types .= 'd';
                else $param_types .= 's';
                $params[] = $value;
            }
            break;
        case 4: // SELECT
            $sql = "SELECT * FROM $table_name";
            if (!empty($where_clause)) {
                $sql .= " WHERE $where_clause";
                // Add WHERE clause parameters
                foreach ($where_data as $value) {
                    if (is_int($value)) $param_types .= 'i';
                    elseif (is_float($value)) $param_types .= 'd';
                    else $param_types .= 's';
                }
            }
            break;
        default:
            $dbActionStatus['message'] = 'Invalid operation.';
            return $dbActionStatus;
    }

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if (!empty($param_types) && !empty($params)) {
            mysqli_stmt_bind_param($stmt, $param_types, ...$params);
        }

        if ($operation === 4) {
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $dbActionStatus['success'] = true;
            $dbActionStatus['data'] = mysqli_fetch_all($result, MYSQLI_ASSOC);
        } else {
            if (mysqli_stmt_execute($stmt)) {
                $dbActionStatus['success'] = true;
                $dbActionStatus['message'] = 'Operation successful.';
                if ($operation === 1 && $stmt->insert_id) {
                    $dbActionStatus['insert_id'] = $stmt->insert_id;
                }
            } else {
                $dbActionStatus['message'] = 'Error executing statement: ' . mysqli_stmt_error($stmt);
            }
        }
        mysqli_stmt_close($stmt);
    } else {
        $dbActionStatus['message'] = 'Error preparing statement: ' . mysqli_error($conn);
    }

    mysqli_close($conn);
    return $dbActionStatus;
}


// --- API Endpoint Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Set headers to return JSON
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action.'];

    // This switch statement handles all API requests
    switch ($action) {
        case 'login':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            // NOTE: In a real application, we would use password_verify() with a hashed password
            $result = secureDbAction('users', 4, [], 'username = ? AND password_hash = ?', [$username, $password]);
            if ($result['success'] && count($result['data']) > 0) {
                $user = $result['data'][0];
                $response = [
                    'success' => true,
                    'message' => 'Login successful.',
                    'role' => $user['role'],
                    'id' => $user['id'],
                    'youtuber_id' => $user['youtuber_id'],
                    'sponsor_id' => $user['sponsor_id']
                ];
            } else {
                $response = ['success' => false, 'message' => 'Invalid username or password.'];
            }
            break;
        case 'register':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';

            if (empty($username) || empty($password) || empty($role)) {
                 $response = ['success' => false, 'message' => 'Missing required fields.'];
                 break;
            }

            // Check if username already exists
            $userCheck = secureDbAction('users', 4, [], 'username = ?', [$username]);
            if ($userCheck['success'] && count($userCheck['data']) > 0) {
                $response = ['success' => false, 'message' => 'Username already exists.'];
                break;
            }

            // Create a temporary profile first
            $profileId = NULL;
            if ($role === 'youtuber') {
                $profileData = ['name' => $username, 'subscribers' => 0, 'niche' => 'New', 'engagement' => 0.0, 'bio' => 'A new YouTuber profile.'];
                $insertResult = secureDbAction('youtubers', 1, $profileData);
                if ($insertResult['success']) {
                    $profileId = $insertResult['insert_id'];
                }
            } else if ($role === 'sponsor') {
                $profileData = ['name' => $username, 'industry' => 'New', 'budget' => 'Low', 'description' => 'A new sponsor profile.'];
                $insertResult = secureDbAction('sponsors', 1, $profileData);
                if ($insertResult['success']) {
                    $profileId = $insertResult['insert_id'];
                }
            }

            if ($profileId === NULL) {
                 $response = ['success' => false, 'message' => 'Could not create profile.'];
                 break;
            }

            // Create user account and link to profile
            $userData = [
                'username' => $username,
                'password_hash' => $password,
                'role' => $role
            ];
            if ($role === 'youtuber') {
                 $userData['youtuber_id'] = $profileId;
            } else if ($role === 'sponsor') {
                 $userData['sponsor_id'] = $profileId;
            }

            $registerResult = secureDbAction('users', 1, $userData);
            if ($registerResult['success']) {
                 $response = ['success' => true, 'message' => 'Registration successful!'];
            } else {
                $table = ($role === 'youtuber') ? 'youtubers' : 'sponsors';
                secureDbAction($table, 3, [], 'id = ?', [$profileId]);
                $response = ['success' => false, 'message' => 'Registration failed: ' . $registerResult['message']];
            }
            break;
        case 'getYoutubers':
            $result = secureDbAction('youtubers', 4);
            $response = ['success' => $result['success'], 'data' => $result['data']];
            break;
        case 'getSponsors':
            $result = secureDbAction('sponsors', 4);
            $response = ['success' => $result['success'], 'data' => $result['data']];
            break;
        case 'getProfile':
            $id = $_GET['id'] ?? 0;
            $type = $_GET['type'] ?? '';
            $table = $type === 'youtuber' ? 'youtubers' : 'sponsors';
            $result = secureDbAction($table, 4, [], 'id = ?', [$id]);
            $response = ['success' => $result['success'], 'data' => ($result['data'][0] ?? null)];
            break;
        case 'sendOffer':
            $youtuberId = $_POST['youtuber_id'] ?? 0;
            $sponsorId = $_POST['sponsor_id'] ?? 0;
            if ($youtuberId && $sponsorId) {
                $result = secureDbAction('collaborations', 4, [], 'youtuber_id = ? AND sponsor_id = ?', [$youtuberId, $sponsorId]);
                if ($result['success'] && count($result['data']) > 0) {
                     $response = ['success' => false, 'message' => 'An offer already exists.'];
                } else {
                    $insertResult = secureDbAction('collaborations', 1, ['youtuber_id' => $youtuberId, 'sponsor_id' => $sponsorId]);
                    $response = ['success' => $insertResult['success'], 'message' => $insertResult['message']];
                }
            } else {
                $response = ['success' => false, 'message' => 'Missing ID(s).'];
            }
            break;
        case 'updateProfile':
            $id = $_POST['id'] ?? 0;
            $type = $_POST['type'] ?? '';
            $table = $type === 'youtuber' ? 'youtubers' : 'sponsors';
            $field = $_POST['field'] ?? '';
            $value = $_POST['value'] ?? '';
            if ($id && $table && $field && $value) {
                $data = [$field => $value];
                $result = secureDbAction($table, 2, $data, 'id = ?', [$id]);
                $response = ['success' => $result['success'], 'message' => $result['message']];
            } else {
                $response = ['success' => false, 'message' => 'Missing data.'];
            }
            break;
        case 'deleteProfile':
            $id = $_POST['id'] ?? 0;
            $type = $_POST['type'] ?? '';
            $table = $type === 'youtuber' ? 'youtubers' : 'sponsors';
            if ($id && $table) {
                $result = secureDbAction($table, 3, [], 'id = ?', [$id]);
                $response = ['success' => $result['success'], 'message' => $result['message']];
            } else {
                $response = ['success' => false, 'message' => 'Missing ID or type.'];
            }
            break;
        case 'addYoutuber':
            $data = [
                'name' => $_POST['name'] ?? '',
                'subscribers' => (int)($_POST['subscribers'] ?? 0),
                'niche' => $_POST['niche'] ?? '',
                'engagement' => (float)($_POST['engagement'] ?? 0.0),
                'image' => $_POST['image'] ?? 'https://placehold.co/150x150/000000/ffffff?text=New',
                'bio' => $_POST['bio'] ?? ''
            ];
            $result = secureDbAction('youtubers', 1, $data);
            $response = ['success' => $result['success'], 'message' => $result['message']];
            break;
        case 'addSponsor':
            $data = [
                'name' => $_POST['name'] ?? '',
                'industry' => $_POST['industry'] ?? '',
                'budget' => $_POST['budget'] ?? '',
                'description' => $_POST['description'] ?? '',
                'logo' => $_POST['logo'] ?? 'https://placehold.co/150x150/000000/ffffff?text=New',
            ];
            $result = secureDbAction('sponsors', 1, $data);
            $response = ['success' => $result['success'], 'message' => $result['message']];
            break;
        // NEW API endpoint for chart data (YouTuber Niche Analysis)
        case 'getNicheAnalysis':
            $conn = OpenCon();
            $sql = "SELECT niche, SUM(subscribers) AS total_subscribers FROM youtubers GROUP BY niche";
            $result = mysqli_query($conn, $sql);
            $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
            $response = ['success' => true, 'data' => $data];
            mysqli_close($conn);
            break;
        // NEW API endpoint for chart data (Sponsor Budget Analysis)
        case 'getBudgetAnalysis':
            $conn = OpenCon();
            $sql = "SELECT budget, COUNT(*) AS sponsor_count FROM sponsors GROUP BY budget";
            $result = mysqli_query($conn, $sql);
            $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
            $response = ['success' => true, 'data' => $data];
            mysqli_close($conn);
            break;
    }
    echo json_encode($response);
    $conn->close();
    exit;
}
?>

<!-- ========================================================================= -->
<!-- HTML Frontend with jQuery & Tailwind CSS -->
<!-- The frontend is now responsible for making API calls to the backend logic above. -->
<!-- ========================================================================= -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced YouTuber & Sponsor Portal</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        @import url('https://rsms.me/inter/inter.css');
        body {
            font-family: 'Inter', sans-serif;
        }
        .container-shadow {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
    <!-- Heroicons for the password toggle -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/2.0.7/css/dataTables.dataTables.min.css">
</head>
<body class="bg-gray-100 antialiased">

    <!-- Navigation Bar -->
    <nav class="bg-white container-shadow p-4 mb-8">
        <div class="container mx-auto flex items-center justify-between">
            <a href="#" class="text-2xl font-bold text-gray-800">SponsorConnect</a>
            <div class="flex items-center space-x-6">
                <button id="view-youtubers-btn" class="text-gray-600 hover:text-gray-900 font-semibold transition-colors duration-200">View YouTubers</button>
                <button id="view-sponsors-btn" class="text-gray-600 hover:text-gray-900 font-semibold transition-colors duration-200">View Sponsors</button>
                <div class="relative">
                    <button id="nav-action-btn" class="bg-indigo-600 text-white font-semibold py-2 px-4 rounded-full hover:bg-indigo-700 transition-colors duration-200">Login</button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <main class="container mx-auto p-4 md:p-8">

        <!-- Dashboard Section -->
        <section id="dashboard-section" class="bg-white p-6 md:p-8 rounded-xl container-shadow">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-6">Welcome to SponsorConnect</h1>
            <p class="text-lg text-gray-600 mb-6">
                This is a conceptual B2B portal for YouTubers and sponsors.
                Use the navigation buttons to explore the different views.
            </p>
            <div class="grid md:grid-cols-2 gap-6 md:gap-8">
                <div class="bg-blue-50 p-6 rounded-lg border border-blue-200">
                    <h2 class="text-2xl font-semibold text-blue-800 mb-2">For YouTubers</h2>
                    <p class="text-gray-700">Browse a list of potential sponsors and their campaigns. Send collaboration offers directly through the portal and manage your partnerships.</p>
                </div>
                <div class="bg-green-50 p-6 rounded-lg border border-green-200">
                    <h2 class="text-2xl font-semibold text-green-800 mb-2">For Sponsors</h2>
                    <p class="text-gray-700">Find the perfect YouTuber for your brand. Filter creators by niche, subscriber count, and engagement. View their profiles and send them sponsorship proposals.</p>
                </div>
            </div>
        </section>

        <!-- Login/Register Section (UPDATED) -->
        <section id="login-section" class="mt-8 hidden bg-white p-6 md:p-8 rounded-xl container-shadow max-w-lg mx-auto">
            <div class="flex border-b border-gray-200 mb-6">
                <button id="login-tab" class="flex-1 py-3 px-4 text-center font-semibold text-gray-800 border-b-2 border-indigo-600 focus:outline-none">Login</button>
                <button id="register-tab" class="flex-1 py-3 px-4 text-center font-semibold text-gray-500 hover:text-gray-800 focus:outline-none">Register</button>
            </div>
            <!-- Login Form -->
            <div id="login-form-container">
                <h2 class="text-3xl font-bold text-gray-900 mb-6 text-center">Login</h2>
                <form id="login-form">
                    <div class="mb-4">
                        <label for="username" class="block text-gray-700 font-semibold mb-2">Username</label>
                        <input type="text" id="username" name="username" class="w-full p-2 border rounded-md" required>
                    </div>
                    <div class="mb-6 relative">
                        <label for="password" class="block text-gray-700 font-semibold mb-2">Password</label>
                        <input type="password" id="password" name="password" class="w-full p-2 border rounded-md pr-10" required>
                        <span id="toggle-password" class="absolute inset-y-0 right-0 top-6 flex items-center pr-3 cursor-pointer">
                            <i class="fas fa-eye text-gray-500"></i>
                        </span>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white font-semibold py-3 px-6 rounded-full hover:bg-indigo-700 transition-colors duration-200">Login</button>
                    <div class="mt-4 text-center">
                        <p class="text-gray-500 text-sm">Use these credentials for testing:</p>
                        <p class="text-gray-500 text-xs">Admin: admin/adminpass | YouTuber: techguru/tgpass | Sponsor: codeforge/cfpass</p>
                    </div>
                </form>
            </div>
            <!-- Register Form (Hidden by default) -->
            <div id="register-form-container" class="hidden">
                <h2 class="text-3xl font-bold text-gray-900 mb-6 text-center">Register</h2>
                <form id="register-form">
                    <div class="mb-4">
                        <label for="register-username" class="block text-gray-700 font-semibold mb-2">Username</label>
                        <input type="text" id="register-username" name="username" class="w-full p-2 border rounded-md" required>
                    </div>
                    <div class="mb-4 relative">
                        <label for="register-password" class="block text-gray-700 font-semibold mb-2">Password</label>
                        <input type="password" id="register-password" name="password" class="w-full p-2 border rounded-md pr-10" required>
                        <span id="toggle-register-password" class="absolute inset-y-0 right-0 top-6 flex items-center pr-3 cursor-pointer">
                            <i class="fas fa-eye text-gray-500"></i>
                        </span>
                    </div>
                    <div class="mb-6">
                        <label for="register-role" class="block text-gray-700 font-semibold mb-2">I am a...</label>
                        <select id="register-role" name="role" class="w-full p-2 border rounded-md" required>
                            <option value="youtuber">YouTuber</option>
                            <option value="sponsor">Sponsor</option>
                        </select>
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white font-semibold py-3 px-6 rounded-full hover:bg-indigo-700 transition-colors duration-200">Register</button>
                </form>
            </div>
        </section>

        <!-- Dynamic Content Section -->
        <section id="dynamic-content-section" class="mt-8 hidden">
            <h2 id="section-title" class="text-3xl font-bold text-gray-900 mb-6"></h2>
            <div id="card-container" class="grid sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Content will be injected here by jQuery -->
            </div>
        </section>

        <!-- Profile Detail Section (Hidden by default) -->
        <section id="profile-detail-section" class="mt-8 hidden bg-white p-6 md:p-8 rounded-xl container-shadow">
            <button id="back-btn" class="text-indigo-600 hover:text-indigo-800 font-semibold mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
                Back to list
            </button>
            <div id="profile-content">
                <!-- Profile details will be injected here by jQuery -->
            </div>
        </section>

        <!-- Admin Panel Section (UPDATED) -->
        <section id="admin-panel-section" class="mt-8 hidden bg-white p-6 md:p-8 rounded-xl container-shadow">
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Admin Dashboard</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                 <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Total Subscribers by Niche</h3>
                    <canvas id="youtuberNicheChart"></canvas>
                </div>
                 <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Sponsor Budget Distribution</h3>
                    <canvas id="sponsorBudgetChart"></canvas>
                </div>
            </div>

            <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 mt-6">
                <h3 class="text-2xl font-semibold text-gray-900 mb-2">Manage YouTubers</h3>
                 <button id="add-youtuber-btn" class="bg-indigo-600 text-white font-semibold py-2 px-4 rounded-full hover:bg-indigo-700 transition-colors duration-200 mb-4">Add New YouTuber</button>
                <table id="youtubersTable" class="display w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                        <tr>
                            <th scope="col" class="py-3 px-6">Name</th>
                            <th scope="col" class="py-3 px-6">Subscribers</th>
                            <th scope="col" class="py-3 px-6">Niche</th>
                            <th scope="col" class="py-3 px-6">Engagement</th>
                            <th scope="col" class="py-3 px-6">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>

            <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 mt-6">
                <h3 class="text-2xl font-semibold text-gray-900 mb-2">Manage Sponsors</h3>
                <button id="add-sponsor-btn" class="bg-indigo-600 text-white font-semibold py-2 px-4 rounded-full hover:bg-indigo-700 transition-colors duration-200 mb-4">Add New Sponsor</button>
                <table id="sponsorsTable" class="display w-full text-sm text-left text-gray-500">
                     <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                        <tr>
                            <th scope="col" class="py-3 px-6">Name</th>
                            <th scope="col" class="py-3 px-6">Industry</th>
                            <th scope="col" class="py-3 px-6">Budget</th>
                            <th scope="col" class="py-3 px-6">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </section>


        <!-- Message/Action Modal -->
        <div id="modal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-600 bg-opacity-50 hidden">
            <div class="flex items-center justify-center min-h-screen">
                <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:max-w-lg p-6">
                    <div class="text-center">
                        <h3 class="text-xl leading-6 font-medium text-gray-900" id="modal-title"></h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500" id="modal-message"></p>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-center space-x-4">
                        <button type="button" id="modal-close-btn" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                            OK
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal for Admin Panel -->
        <div id="edit-modal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-600 bg-opacity-50 hidden">
            <div class="flex items-center justify-center min-h-screen">
                <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:max-w-lg p-6">
                    <div class="text-center">
                        <h3 class="text-xl leading-6 font-medium text-gray-900 mb-4">Edit Profile</h3>
                        <form id="edit-form">
                            <input type="hidden" id="edit-id">
                            <input type="hidden" id="edit-type">
                            <div class="mb-4 text-left">
                                <label for="edit-field" class="block text-gray-700 font-semibold mb-2">Field to Edit</label>
                                <select id="edit-field" class="w-full p-2 border rounded-md">
                                    <!-- Options will be dynamically populated -->
                                </select>
                            </div>
                            <div class="mb-4 text-left">
                                <label for="edit-value" class="block text-gray-700 font-semibold mb-2">New Value</label>
                                <input type="text" id="edit-value" class="w-full p-2 border rounded-md">
                            </div>
                            <div class="mt-4 flex justify-end space-x-4">
                                <button type="button" id="edit-modal-cancel-btn" class="py-2 px-4 rounded-md text-gray-700 hover:bg-gray-200 transition-colors">Cancel</button>
                                <button type="submit" class="bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition-colors">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add YouTuber Modal -->
        <div id="add-youtuber-modal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-600 bg-opacity-50 hidden">
            <div class="flex items-center justify-center min-h-screen">
                <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:max-w-lg p-6">
                    <div class="text-center">
                        <h3 class="text-xl leading-6 font-medium text-gray-900 mb-4">Add New YouTuber</h3>
                        <form id="add-youtuber-form">
                            <div class="mb-4 text-left">
                                <label for="add-youtuber-name" class="block text-gray-700 font-semibold mb-2">Name</label>
                                <input type="text" id="add-youtuber-name" name="name" class="w-full p-2 border rounded-md" required>
                            </div>
                            <div class="mb-4 text-left">
                                <label for="add-youtuber-subscribers" class="block text-gray-700 font-semibold mb-2">Subscribers</label>
                                <input type="number" id="add-youtuber-subscribers" name="subscribers" class="w-full p-2 border rounded-md" required>
                            </div>
                            <div class="mb-4 text-left">
                                <label for="add-youtuber-niche" class="block text-gray-700 font-semibold mb-2">Niche</label>
                                <input type="text" id="add-youtuber-niche" name="niche" class="w-full p-2 border rounded-md" required>
                            </div>
                            <div class="mb-4 text-left">
                                <label for="add-youtuber-engagement" class="block text-gray-700 font-semibold mb-2">Engagement (%)</label>
                                <input type="number" step="0.01" id="add-youtuber-engagement" name="engagement" class="w-full p-2 border rounded-md" required>
                            </div>
                            <div class="mb-4 text-left">
                                <label for="add-youtuber-bio" class="block text-gray-700 font-semibold mb-2">Bio</label>
                                <textarea id="add-youtuber-bio" name="bio" rows="3" class="w-full p-2 border rounded-md" required></textarea>
                            </div>
                             <div class="mb-4 text-left">
                                <label for="add-youtuber-image" class="block text-gray-700 font-semibold mb-2">Image URL</label>
                                <input type="text" id="add-youtuber-image" name="image" class="w-full p-2 border rounded-md">
                            </div>
                            <div class="mt-4 flex justify-end space-x-4">
                                <button type="button" id="add-youtuber-modal-cancel-btn" class="py-2 px-4 rounded-md text-gray-700 hover:bg-gray-200 transition-colors">Cancel</button>
                                <button type="submit" class="bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition-colors">Add YouTuber</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Sponsor Modal (NEW) -->
        <div id="add-sponsor-modal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-600 bg-opacity-50 hidden">
            <div class="flex items-center justify-center min-h-screen">
                <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:max-w-lg p-6">
                    <div class="text-center">
                        <h3 class="text-xl leading-6 font-medium text-gray-900 mb-4">Add New Sponsor</h3>
                        <form id="add-sponsor-form">
                            <div class="mb-4 text-left">
                                <label for="add-sponsor-name" class="block text-gray-700 font-semibold mb-2">Name</label>
                                <input type="text" id="add-sponsor-name" name="name" class="w-full p-2 border rounded-md" required>
                            </div>
                            <div class="mb-4 text-left">
                                <label for="add-sponsor-industry" class="block text-gray-700 font-semibold mb-2">Industry</label>
                                <input type="text" id="add-sponsor-industry" name="industry" class="w-full p-2 border rounded-md" required>
                            </div>
                            <div class="mb-4 text-left">
                                <label for="add-sponsor-budget" class="block text-gray-700 font-semibold mb-2">Budget</label>
                                <select id="add-sponsor-budget" name="budget" class="w-full p-2 border rounded-md">
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                            <div class="mb-4 text-left">
                                <label for="add-sponsor-description" class="block text-gray-700 font-semibold mb-2">Description</label>
                                <textarea id="add-sponsor-description" name="description" rows="3" class="w-full p-2 border rounded-md" required></textarea>
                            </div>
                             <div class="mb-4 text-left">
                                <label for="add-sponsor-logo" class="block text-gray-700 font-semibold mb-2">Logo URL</label>
                                <input type="text" id="add-sponsor-logo" name="logo" class="w-full p-2 border rounded-md">
                            </div>
                            <div class="mt-4 flex justify-end space-x-4">
                                <button type="button" id="add-sponsor-modal-cancel-btn" class="py-2 px-4 rounded-md text-gray-700 hover:bg-gray-200 transition-colors">Cancel</button>
                                <button type="submit" class="bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 transition-colors">Add Sponsor</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- jQuery CDN -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Chart.js CDN for Graphs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- DataTables CDN -->
    <script src="https://cdn.datatables.net/2.0.7/js/dataTables.min.js"></script>


    <!-- ========================================================================= -->
    <!-- jQuery & JavaScript Frontend Logic -->
    <!-- This section handles all asynchronous data fetching and DOM manipulation.  -->
    <!-- ========================================================================= -->
    <script>
    $(document).ready(function() {

        // --- Core State Management ---
        let loggedInUser = null; // Stores the user object after login
        let youtubersTable, sponsorsTable;

        // --- Core Functions ---
        function apiCall(action, method, data, successCallback) {
            $.ajax({
                url: window.location.href, // Send the request to this same file
                type: method,
                data: $.extend({ action: action }, data),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (successCallback) successCallback(response.data);
                    } else {
                        showModal('Error', response.message);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    showModal('API Error', 'An error occurred while communicating with the server: ' + errorThrown);
                }
            });
        }

        function showSection(sectionId) {
            $('#dashboard-section, #login-section, #dynamic-content-section, #profile-detail-section, #admin-panel-section').addClass('hidden');
            $(sectionId).removeClass('hidden');
        }

        function renderNavbar() {
            const navBtn = $('#nav-action-btn');
            navBtn.off('click'); // Clear previous click handlers
            if (loggedInUser) {
                if (loggedInUser.role === 'admin') {
                    navBtn.text('Admin Panel').removeClass('bg-indigo-600').addClass('bg-pink-600');
                    navBtn.on('click', renderAdminPanel);
                } else if (loggedInUser.role === 'youtuber') {
                    navBtn.text('My Profile').removeClass('bg-indigo-600').addClass('bg-purple-600');
                    navBtn.on('click', () => renderProfile(loggedInUser.youtuber_id, 'youtuber'));
                } else if (loggedInUser.role === 'sponsor') {
                    navBtn.text('My Profile').removeClass('bg-indigo-600').addClass('bg-green-600');
                    navBtn.on('click', () => renderProfile(loggedInUser.sponsor_id, 'sponsor'));
                }
                const logoutBtn = $('<button id="logout-btn" class="text-gray-600 hover:text-gray-900 font-semibold transition-colors duration-200">Logout</button>');
                $('#nav-action-btn').before(logoutBtn);
            } else {
                navBtn.text('Login').removeClass('bg-pink-600 bg-purple-600 bg-green-600').addClass('bg-indigo-600');
                navBtn.on('click', () => showSection('#login-section'));
                $('#logout-btn').remove();
            }
        }

        // --- Rendering Functions ---
        function renderYoutubers() {
            showSection('#dynamic-content-section');
            $('#section-title').text('Browse All YouTubers');
            const container = $('#card-container');
            container.empty();
            apiCall('getYoutubers', 'GET', {}, function(youtubers) {
                if (youtubers.length === 0) {
                     container.html('<p class="text-center text-gray-500 text-lg">No YouTubers found.</p>');
                     return;
                }
                $.each(youtubers, function(index, youtuber) {
                    const card = `
                        <div class="bg-white rounded-xl p-6 container-shadow flex flex-col items-center text-center">
                            <img src="${youtuber.image}" alt="${youtuber.name} profile" class="w-24 h-24 rounded-full mb-4 object-cover">
                            <h3 class="text-2xl font-semibold text-gray-900">${youtuber.name}</h3>
                            <p class="text-md text-gray-500 mb-2">${youtuber.niche}</p>
                            <p class="text-sm text-gray-600 font-medium">Subscribers: ${formatNumber(youtuber.subscribers)}</p>
                            <p class="text-sm text-gray-600 font-medium">Engagement: ${youtuber.engagement}%</p>
                            <button class="mt-4 bg-indigo-500 text-white font-semibold py-2 px-6 rounded-full hover:bg-indigo-600 transition-colors duration-200 view-profile-btn" data-id="${youtuber.id}" data-type="youtuber">View Profile</button>
                        </div>
                    `;
                    container.append(card);
                });
            });
        }

        function renderSponsors() {
            showSection('#dynamic-content-section');
            $('#section-title').text('Browse All Sponsors');
            const container = $('#card-container');
            container.empty();
            apiCall('getSponsors', 'GET', {}, function(sponsors) {
                 if (sponsors.length === 0) {
                     container.html('<p class="text-center text-gray-500 text-lg">No Sponsors found.</p>');
                     return;
                }
                $.each(sponsors, function(index, sponsor) {
                    const card = `
                        <div class="bg-white rounded-xl p-6 container-shadow flex flex-col items-center text-center">
                            <img src="${sponsor.logo}" alt="${sponsor.name} logo" class="w-24 h-24 rounded-full mb-4 object-cover">
                            <h3 class="text-2xl font-semibold text-gray-900">${sponsor.name}</h3>
                            <p class="text-md text-gray-500 mb-2">${sponsor.industry}</p>
                            <p class="text-sm text-gray-600 font-medium">Budget: ${sponsor.budget}</p>
                            <button class="mt-4 bg-green-500 text-white font-semibold py-2 px-6 rounded-full hover:bg-green-600 transition-colors duration-200 view-profile-btn" data-id="${sponsor.id}" data-type="sponsor">View Profile</button>
                        </div>
                    `;
                    container.append(card);
                });
            });
        }

        function renderProfile(id, type) {
            showSection('#profile-detail-section');
            const profileContent = $('#profile-content');
            profileContent.empty();
            apiCall('getProfile', 'GET', { id: id, type: type }, function(item) {
                if (!item) {
                    profileContent.html('<p class="text-center text-gray-500">Profile not found.</p>');
                    return;
                }
                let buttonText = type === 'youtuber' ? 'Send Sponsorship Offer' : 'Propose Collaboration';
                let buttonColor = type === 'youtuber' ? 'bg-green-500 hover:bg-green-600' : 'bg-indigo-500 hover:bg-indigo-600';
                let primaryInfo = type === 'youtuber' ? item.niche : item.industry;
                let secondaryInfo = type === 'youtuber' ? item.bio : item.description;
                let imageUrl = type === 'youtuber' ? item.image : item.logo;
                const profileHtml = `
                    <div class="flex flex-col md:flex-row items-center md:items-start space-y-6 md:space-y-0 md:space-x-8">
                        <img src="${imageUrl}" onerror="this.onerror=null;this.src='https://placehold.co/150x150/000000/ffffff?text=Image+Not+Found';" alt="${item.name} profile" class="w-32 h-32 md:w-48 md:h-48 rounded-full object-cover container-shadow">
                        <div class="text-center md:text-left">
                            <h2 class="text-3xl md:text-4xl font-bold text-gray-900">${item.name}</h2>
                            <p class="text-lg text-gray-600 mb-4">${primaryInfo}</p>
                            <p class="text-gray-700 leading-relaxed max-w-2xl mb-6">${secondaryInfo}</p>
                            <div class="flex flex-col sm:flex-row justify-center md:justify-start sm:space-x-4 space-y-4 sm:space-y-0">
                                <button class="flex-grow sm:flex-grow-0 text-white font-semibold py-3 px-8 rounded-full transition-colors duration-200 ${buttonColor} offer-btn" data-id="${item.id}" data-type="${type}">
                                    ${buttonText}
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                profileContent.html(profileHtml);
            });
        }

        function renderAdminPanel() {
            // Check if user is logged in and has admin role
            if (!loggedInUser || loggedInUser.role !== 'admin') {
                showModal('Access Denied', 'You must be logged in as an admin to view this page.');
                return;
            }
            showSection('#admin-panel-section');

            // Render charts
            renderNicheChart();
            renderBudgetChart();

            // Render DataTables
            loadYoutubersTable();
            loadSponsorsTable();
        }

        // NEW: Chart Rendering Functions
        function renderNicheChart() {
            apiCall('getNicheAnalysis', 'GET', {}, function(data) {
                const labels = data.map(item => item.niche);
                const subscribers = data.map(item => item.total_subscribers);
                const ctx = document.getElementById('youtuberNicheChart').getContext('2d');
                if (window.youtuberNicheChart instanceof Chart) {
                    window.youtuberNicheChart.destroy();
                }
                window.youtuberNicheChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Total Subscribers',
                            data: subscribers,
                            backgroundColor: 'rgba(75, 192, 192, 0.6)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            });
        }

        function renderBudgetChart() {
            apiCall('getBudgetAnalysis', 'GET', {}, function(data) {
                const labels = data.map(item => item.budget);
                const counts = data.map(item => item.sponsor_count);
                const ctx = document.getElementById('sponsorBudgetChart').getContext('2d');
                if (window.sponsorBudgetChart instanceof Chart) {
                    window.sponsorBudgetChart.destroy();
                }
                window.sponsorBudgetChart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Number of Sponsors',
                            data: counts,
                            backgroundColor: ['#4F46E5', '#22C55E', '#EF4444'],
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true
                    }
                });
            });
        }

        // NEW: DataTables Loading Functions
        function loadYoutubersTable() {
             // Destroy existing table instance if it exists
             if ($.fn.DataTable.isDataTable('#youtubersTable')) {
                 youtubersTable.destroy();
             }
             apiCall('getYoutubers', 'GET', {}, function(youtubers) {
                 youtubersTable = $('#youtubersTable').DataTable({
                     data: youtubers,
                     columns: [
                         { data: 'name' },
                         { data: 'subscribers' },
                         { data: 'niche' },
                         { data: 'engagement' },
                         {
                             data: null,
                             render: function (data, type, row) {
                                 return `<button class="edit-btn text-sm font-medium text-blue-600 hover:text-blue-800" data-id="${row.id}" data-type="youtuber">Edit</button> | <button class="delete-btn text-sm font-medium text-red-600 hover:text-red-800" data-id="${row.id}" data-type="youtuber">Delete</button>`;
                             }
                         }
                     ]
                 });
             });
         }

        function loadSponsorsTable() {
            // Destroy existing table instance if it exists
            if ($.fn.DataTable.isDataTable('#sponsorsTable')) {
                sponsorsTable.destroy();
            }
            apiCall('getSponsors', 'GET', {}, function(sponsors) {
                 sponsorsTable = $('#sponsorsTable').DataTable({
                     data: sponsors,
                     columns: [
                         { data: 'name' },
                         { data: 'industry' },
                         { data: 'budget' },
                         {
                             data: null,
                             render: function (data, type, row) {
                                 return `<button class="edit-btn text-sm font-medium text-blue-600 hover:text-blue-800" data-id="${row.id}" data-type="sponsor">Edit</button> | <button class="delete-btn text-sm font-medium text-red-600 hover:text-red-800" data-id="${row.id}" data-type="sponsor">Delete</button>`;
                             }
                         }
                     ]
                 });
            });
        }

        // --- Event Listeners for Navigation and Actions ---
        $('#view-youtubers-btn').on('click', renderYoutubers);
        $('#view-sponsors-btn').on('click', renderSponsors);

        // View Profile button listener (using delegation)
        $(document).on('click', '.view-profile-btn', function() {
            const id = $(this).data('id');
            const type = $(this).data('type');
            renderProfile(id, type);
        });

        // Back button listener
        $('#back-btn').on('click', function() {
            if ($('#section-title').text().includes('YouTubers')) {
                renderYoutubers();
            } else if ($('#section-title').text().includes('Sponsors')) {
                renderSponsors();
            } else {
                showSection('#dashboard-section');
            }
        });

        // Send Offer button listener
        $(document).on('click', '.offer-btn', function() {
            if (!loggedInUser || (loggedInUser.role !== 'youtuber' && loggedInUser.role !== 'sponsor')) {
                showModal('Access Denied', 'You must be logged in as a YouTuber or Sponsor to send offers.');
                return;
            }
            const id = $(this).data('id');
            const type = $(this).data('type');
            let youtuberId = loggedInUser.youtuber_id;
            let sponsorId = loggedInUser.sponsor_id;
            if (loggedInUser.role === 'youtuber') {
                sponsorId = id;
            } else {
                youtuberId = id;
            }
            apiCall('sendOffer', 'POST', { youtuber_id: youtuberId, sponsor_id: sponsorId }, function() {
                showModal('Offer Sent!', 'Your collaboration proposal has been sent successfully.');
            });
        });

        // Delete button listener (Admin Panel) - Now delegated from the table body
        $(document).on('click', '#youtubersTable .delete-btn, #sponsorsTable .delete-btn', function() {
            const id = $(this).data('id');
            const type = $(this).data('type');
            if (confirm(`Are you sure you want to delete this ${type}? This action cannot be undone.`)) {
                apiCall('deleteProfile', 'POST', { id: id, type: type }, function() {
                    showModal('Success', 'Profile deleted successfully.', renderAdminPanel);
                });
            }
        });

        // Edit button listener (Admin Panel) - Now delegated from the table body
        $(document).on('click', '#youtubersTable .edit-btn, #sponsorsTable .edit-btn', function() {
            const id = $(this).data('id');
            const type = $(this).data('type');
            $('#edit-id').val(id);
            $('#edit-type').val(type);
            const fields = type === 'youtuber' ?
                { name: 'Name', niche: 'Niche', subscribers: 'Subscribers', engagement: 'Engagement', bio: 'Bio', image: 'Image URL' } :
                { name: 'Name', industry: 'Industry', budget: 'Budget', description: 'Description', logo: 'Logo URL' };
            const select = $('#edit-field');
            select.empty();
            $.each(fields, function(key, value) {
                select.append($('<option>', { value: key, text: value }));
            });
            $('#edit-modal').removeClass('hidden');
        });

        // Edit form submission listener
        $('#edit-form').on('submit', function(e) {
            e.preventDefault();
            const id = $('#edit-id').val();
            const type = $('#edit-type').val();
            const field = $('#edit-field').val();
            const value = $('#edit-value').val();
            apiCall('updateProfile', 'POST', { id, type, field, value }, function() {
                showModal('Success', 'Profile updated successfully.', function() {
                    $('#edit-modal').addClass('hidden');
                    renderAdminPanel();
                });
            });
        });

        $('#edit-modal-cancel-btn').on('click', function() {
            $('#edit-modal').addClass('hidden');
        });

        // Add YouTuber button listener (Admin Panel)
        $('#add-youtuber-btn').on('click', function() {
            $('#add-youtuber-modal').removeClass('hidden');
        });
        $('#add-youtuber-modal-cancel-btn').on('click', function() {
            $('#add-youtuber-modal').addClass('hidden');
            $('#add-youtuber-form')[0].reset();
        });

        // Add YouTuber form submission
        $('#add-youtuber-form').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            apiCall('addYoutuber', 'POST', formData, function() {
                showModal('Success', 'YouTuber added successfully.', function() {
                    $('#add-youtuber-modal').addClass('hidden');
                    $('#add-youtuber-form')[0].reset();
                    renderAdminPanel();
                });
            });
        });

        // Add Sponsor button listener (Admin Panel)
        $('#add-sponsor-btn').on('click', function() {
            $('#add-sponsor-modal').removeClass('hidden');
        });
        $('#add-sponsor-modal-cancel-btn').on('click', function() {
            $('#add-sponsor-modal').addClass('hidden');
            $('#add-sponsor-form')[0].reset();
        });

        // Add Sponsor form submission
        $('#add-sponsor-form').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            apiCall('addSponsor', 'POST', formData, function() {
                showModal('Success', 'Sponsor added successfully.', function() {
                    $('#add-sponsor-modal').addClass('hidden');
                    $('#add-sponsor-form')[0].reset();
                    renderAdminPanel();
                });
            });
        });


        // Login form submission
        $('#login-form').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData + '&action=login',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        loggedInUser = response;
                        showModal('Login Successful!', 'Welcome, ' + response.role + '!');
                        showSection('#dashboard-section');
                        renderNavbar();
                    } else {
                        showModal('Login Failed', response.message);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    showModal('API Error', 'An error occurred during login: ' + errorThrown);
                }
            });
        });

        // Register form submission (NEW)
        $('#register-form').on('submit', function(e) {
            e.preventDefault();
            const username = $('#register-username').val();
            const password = $('#register-password').val();
            if (username.length < 3 || password.length < 3) {
                 showModal('Validation Error', 'Username and password must be at least 3 characters long.');
                 return;
            }
            const formData = $(this).serialize();
            apiCall('register', 'POST', formData, function(data) {
                showModal('Registration Successful!', 'Your account has been created. You can now log in.');
                $('#login-tab').click(); // Switch back to login form
            });
        });


        // Logout listener
        $(document).on('click', '#logout-btn', function() {
            loggedInUser = null;
            showModal('Logged Out', 'You have been logged out successfully.', function() {
                showSection('#dashboard-section');
                renderNavbar();
            });
        });


        // --- UI Logic & Utility Functions ---
        // Login/Register Tab Toggling
        $('#login-tab').on('click', function() {
            $(this).removeClass('text-gray-500').addClass('text-gray-800 border-indigo-600').css('border-bottom-width', '2px');
            $('#register-tab').removeClass('text-gray-800 border-indigo-600').addClass('text-gray-500').css('border-bottom-width', '0px');
            $('#login-form-container').removeClass('hidden');
            $('#register-form-container').addClass('hidden');
        });
        $('#register-tab').on('click', function() {
            $(this).removeClass('text-gray-500').addClass('text-gray-800 border-indigo-600').css('border-bottom-width', '2px');
            $('#login-tab').removeClass('text-gray-800 border-indigo-600').addClass('text-gray-500').css('border-bottom-width', '0px');
            $('#register-form-container').removeClass('hidden');
            $('#login-form-container').addClass('hidden');
        });

        // Show/Hide Password for Login
        $('#toggle-password').on('click', function() {
            const passwordInput = $('#password');
            const icon = $(this).find('i');
            if (passwordInput.attr('type') === 'password') {
                passwordInput.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                passwordInput.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });

        // Show/Hide Password for Register
        $('#toggle-register-password').on('click', function() {
            const passwordInput = $('#register-password');
            const icon = $(this).find('i');
            if (passwordInput.attr('type') === 'password') {
                passwordInput.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                passwordInput.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });

        // Modal functions
        function showModal(title, message, callback = null) {
            $('#modal-title').text(title);
            $('#modal-message').text(message);
            $('#modal').removeClass('hidden');
            $('#modal-close-btn').off('click').on('click', function() {
                $('#modal').addClass('hidden');
                if (callback) {
                    callback();
                }
            });
        }

        // Helper function for formatting large numbers
        function formatNumber(num) {
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            }
            if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num;
        }

        // --- Initial View ---
        showSection('#dashboard-section');
        renderNavbar();
    });
    </script>

</body>
</html>
