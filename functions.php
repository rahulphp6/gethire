<?php
require_once('connect.php');
function OpenCon() {
    global $conn;
    return $conn;
}
function secureDbAction($table_name, $operation, $form_data = [], $where_clause = '', $where_data = []) {
    global $conn;
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

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $dbActionStatus['message'] = 'SQL prepare failed: ' . $conn->error;
        return $dbActionStatus;
    }

    if (!empty($param_types) && !empty($params)) {
        $stmt->bind_param($param_types, ...$params);
        if ($stmt->error) {
            $dbActionStatus['message'] = 'SQL bind failed: ' . $stmt->error.' Query: '.$sql;
            $stmt->close();
            return $dbActionStatus;
        }
    }

    if ($operation === 4) {
        $stmt->execute();
        if ($stmt->error) {
            $dbActionStatus['message'] = 'SQL execute failed: ' . $stmt->error.' Query: '.$sql;
            $stmt->close();
            return $dbActionStatus;
        }
        $result = $stmt->get_result();
        if ($result === false) {
            $dbActionStatus['message'] = 'SQL get_result failed: ' . $stmt->error.' Query: '.$sql;
            $stmt->close();
            return $dbActionStatus;
        }
        $dbActionStatus['success'] = true;
        $dbActionStatus['data'] = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $stmt->execute();
        if ($stmt->error) {
            $dbActionStatus['message'] = 'Error executing statement: ' . $stmt->error.' Query: '.$sql;
        } else {
            $dbActionStatus['success'] = true;
            $dbActionStatus['message'] = 'Operation successful.';
            if ($operation === 1 && $stmt->insert_id) {
                $dbActionStatus['insert_id'] = $stmt->insert_id;
            }
        }
    }
    $stmt->close();
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
            // NOTE: In this demo, we use a simple plain text comparison.
            $result = secureDbAction('users', 4, [], 'username ="'.$username.'" AND password_hash ="'.$password.'"');
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
                // Return the detailed error message from the database action if available, otherwise use a generic one.
                $response = ['success' => false, 'message' => $result['message'] ?: 'Invalid username or password.'];
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
            $userCheck = secureDbAction('users', 4, [], 'username ="'.$username.'" ?');
            if ($userCheck['success'] && count($userCheck['data']) > 0) {
                $response = ['success' => false, 'message' => 'Username already exists.'];
                break;
            }

            // Create a temporary profile first
            $profileId = NULL;
            $insertResult = ['success' => false];
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
                 $response = ['success' => false, 'message' => $insertResult['message'] ?: 'Could not create profile.'];
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
                secureDbAction($table, 3, [], 'id ='.$profileId);
                $response = ['success' => false, 'message' => 'Registration failed: ' . $registerResult['message']];
            }
            break;
        case 'getYoutubers':
            $result = secureDbAction('youtubers', 4);
            $response = ['success' => $result['success'], 'data' => $result['data'], 'message' => $result['message']];
            break;
        case 'getSponsors':
            $result = secureDbAction('sponsors', 4);
            $response = ['success' => $result['success'], 'data' => $result['data'], 'message' => $result['message']];
            break;
        case 'getProfile':
            $id = $_GET['id'] ?? 0;
            $type = $_GET['type'] ?? '';
            $table = $type === 'youtuber' ? 'youtubers' : 'sponsors';
            $result = secureDbAction($table, 4, [], 'id ='.$id);
            $response = ['success' => $result['success'], 'data' => ($result['data'][0] ?? null), 'message' => $result['message']];
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
            $conn->close();
            $conn = new mysqli($servername, $db_username, $db_password, $dbname);
            $sql = "SELECT niche, SUM(subscribers) AS total_subscribers FROM youtubers GROUP BY niche";
            $result = $conn->query($sql);
            if ($result) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                $response = ['success' => true, 'data' => $data, 'message' => ''];
            } else {
                $response = ['success' => false, 'data' => [], 'message' => 'MySQL Error: ' . $conn->error];
            }
            $conn->close();
            break;
        // NEW API endpoint for chart data (Sponsor Budget Analysis)
        case 'getBudgetAnalysis':
            $conn->close();
            $conn = new mysqli($servername, $db_username, $db_password, $dbname);
            $sql = "SELECT budget, COUNT(*) AS sponsor_count FROM sponsors GROUP BY budget";
            $result = $conn->query($sql);
            if ($result) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                $response = ['success' => true, 'data' => $data, 'message' => ''];
            } else {
                $response = ['success' => false, 'data' => [], 'message' => 'MySQL Error: ' . $conn->error];
            }
            $conn->close();
            break;
    }
    echo json_encode($response);
    exit;
}