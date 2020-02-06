<?PHP
    // https://your-website.com
    $wp_web_root = 'https://your-website.com';

    // /path/on/web/server/to/wordress
    $wp_file_root = '/var/www/your-website';

    // Database connection settings...
    $db_host = '';
    $db_user = '';
    $db_pass = '';
    $db_name = '';

    // Path to Facebook backup directory
    // Can be a relative path like 'facebook'
    // or absolute like '/home/someone/facebook'
    $fb_backup_path = 'facebook';

    // Path to Facebook JSON file to import
    // I suggest using the 'posts/your_posts_1.json' file inside your Facebook backup directory
    $fb_posts_json = 'facebook/posts/your_posts_1.json';

    // Path to Instagram backup directory
    // Can be a relative path like 'instagram'
    // or absolute like '/home/someone/instagram'
    $instagram_backup_path = 'instagram';

    // Should the location names you assigned to your FB and Instagram posts be imported?
    // Note: Only the palce name will be imported. ex: "Nashville, TN".
    // Lat/Lng coordidnates and other location data will be ignored (unless it's embedded in
    // the photo/video itself).
    $import_locations = true;

    // Optional: I didn't want the full names of my children plainly obvious and available
    // for random web visitors to see. Add find/replace pairs and they will be replaced in
    // the new post body.
    $replacements = array('Han' => 'Mr. H', 'Chewie' => 'Mr. C');

    // ID of WordPress user to import posts as
    $wp_post_author_id = 1;

    // Should comments and pings be open or closed on the imported posts?...
    $wp_comment_status = 'open'; // or 'closed'
    $wp_ping_status = 'open'; // or 'closed'

    // ###############################
    // NOTHING MORE TO CONFIGURE BELOW
    // ###############################

    date_default_timezone_set('UTC');
    ini_set('display_errors', '1');
    error_reporting(-1);

    define('DOC_ROOT', realpath(dirname(__FILE__)));

    $wp_web_root = rtrim($wp_web_root, '/');
    $wp_file_root = rtrim($wp_file_root, '/');

    $fb_backup_path = rtrim($fb_backup_path, '/');
    $instagram_backup_path = rtrim($instagram_backup_path, '/');

    $db = mysqli_connect($db_host, $db_user, $db_pass) or die('Could not connect to database.');
    mysqli_select_db($db, $db_name) or die('Could not select database.');
    mysqli_set_charset($db, 'utf8mb4') or die('Could not set database charset to utf8mb4.');

    $upload_dir = "$wp_file_root/wp-content/uploads/";
    if(!file_exists($upload_dir)) {
        if(mkdir($upload_dir, 0777, true) === false) {
            die("Could not create WordPress uploads directory at $upload_dir");
        }
    } else {
        if(!is_writable($upload_dir)) {
            die("WordPress uploads directory is not writable at $upload_dir");
        }
    }
