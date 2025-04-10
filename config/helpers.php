<?php
function formatDateForMySQL($date) {
    return date('Y-m-d', strtotime($date));
}
