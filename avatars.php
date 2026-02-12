<?php
/**
 * Avatar Helper Functions
 * Manages avatar URLs and provides helper functions for avatar handling
 */

/**
 * Get all available avatars
 * @return array Array of avatar URLs indexed by avatar ID
 */
function getAvatars() {
    // Images directly in project root - no folders needed
    return [
        1 => 'avatar1.jpg',
        2 => 'avatar2.jpg',
        3 => 'avatar3.jpg',
        4 => 'avatar4.jpg'
    ];
}

/**
 * Get avatar URL by ID
 * @param int $avatarId The avatar ID (1-4)
 * @return string The avatar URL or default avatar if invalid ID
 */
function getAvatarUrl($avatarId) {
    $avatars = getAvatars();
    return $avatars[$avatarId] ?? $avatars[1]; // Default to avatar 1 if invalid ID
}

/**
 * Validate avatar ID
 * @param int $avatarId The avatar ID to validate
 * @return bool True if valid, false otherwise
 */
function isValidAvatarId($avatarId) {
    return is_numeric($avatarId) && $avatarId >= 1 && $avatarId <= 4;
}

/**
 * Get user avatar URL from database
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @return string Avatar URL
 */
function getUserAvatarUrl($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT avatar_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        $avatarId = $result['avatar_id'] ?? 1;
        return getAvatarUrl($avatarId);
    } catch (PDOException $e) {
        // Return default avatar on error
        return getAvatarUrl(1);
    }
}

/**
 * Get user avatar URL by session data
 * @param array $session Session data
 * @return string Avatar URL based on user's first name initial
 */
function getSessionAvatarUrl($session) {
    // This is a fallback function for when you want to use session data
    // You can customize this to use session-stored avatar_id if available
    $initial = strtoupper(substr($session['name'] ?? 'U', 0, 1));
    return "https://via.placeholder.com/40/1e40af/ffffff?text={$initial}";
}
?>