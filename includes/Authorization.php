<?php
/**
 * Authorization compatibility wrapper
 */

class Authorization {
    public static function isAdmin() {
        return Auth::checkRole('admin');
    }

    public static function isStudent() {
        return Auth::checkRole('student');
    }

    public static function requireAdmin() {
        Auth::requireAdmin();
    }

    public static function requireStudent() {
        Auth::requireStudent();
    }
}

?>
