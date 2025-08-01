<?php
session_start();

// Define the system message content for quiz generation
const QUIZ_SYSTEM_MESSAGE_CONTENT = "You are a quiz master. Your task is to generate exactly 10 multiple-choice questions about a given topic. For each question, provide 4 options (A, B, C, D) and clearly indicate the correct answer. Format the entire output as a JSON array of objects, where each object has 'question' (string), 'options' (an array of 4 strings), and 'correct_answer' (string, e.g., 'A', 'B', 'C', 'D'). Ensure the JSON is valid and only contains the array of questions. DO NOT include any other text or formatting outside the JSON array. The questions should be relevant to the provided job field.";


if (isset($_POST['field'])) {
    $userField = $_POST['field'];

    // Construct the prompt for the AI
    $prompt = "Generate 10 multiple-choice questions about \"" . $userField . "\".";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.cohere.ai/v1/chat"); // Use Cohere API
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    
    $payload = [
        "model" => "command-r-plus", // Assuming command-r-plus model for Cohere
        "message" => $prompt,
        "chat_history" => [], // No chat history needed for fresh quiz generation
        "preamble" => QUIZ_SYSTEM_MESSAGE_CONTENT, // Use quiz-specific preamble
        "max_tokens" => 2000 // Increased max_tokens to ensure the full JSON response is received.
    ];

    $json_payload = json_encode($payload);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer QHw20MxzRN9JU1VQUKdovICaOXPONYz86DXdUiqy", // Your Cohere API Key
        "Content-Type: application/json",
        "Accept: application/json"
    ]);
    
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        http_response_code(500);
        echo json_encode(['error' => "Error connecting to the AI service: " . $curl_error]);
    } else {
        $data = json_decode($response, true);

        if ($http_code !== 200) {
            http_response_code($http_code);
            echo json_encode(['error' => "Error from AI service (HTTP " . $http_code . "): " . (isset($data['message']) ? $data['message'] : $response)]);
        } elseif (isset($data['text'])) { // Cohere response uses 'text' field
            $quiz_questions_json_string = $data['text'];
            
            $questions = json_decode($quiz_questions_json_string, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($questions)) {
                echo json_encode(['success' => true, 'questions' => $questions]);
            } else {
                // Attempt to extract JSON from a markdown code block (```json ... ```)
                $parsed_questions = [];
                if (preg_match('/```json\s*(.*?)\s*```/s', $quiz_questions_json_string, $matches)) {
                    $json_part = $matches[1];
                    $parsed_questions_from_markdown = json_decode($json_part, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($parsed_questions_from_markdown)) {
                         $parsed_questions = $parsed_questions_from_markdown;
                    }
                }

                if (empty($parsed_questions)) {
                    http_response_code(500);
                    echo json_encode(['error' => "Failed to parse questions from AI response. The AI might not have returned valid JSON, or the parsing logic needs refinement. Raw AI Text: " . $quiz_questions_json_string]);
                } else {
                    echo json_encode(['success' => true, 'questions' => $parsed_questions]);
                }
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => "Unexpected response format from AI service. 'text' field missing. Raw Response: " . $response]);
        }
    }
    
    curl_close($ch);
    exit;
}
?>
