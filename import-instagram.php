<?PHP
    require 'settings.inc.php';
    require 'markdown.inc.php';

    $media = json_decode(file_get_contents("$instagram_backup_path/media.json"));
    if($media === false) die("Could not open and/or parse $instagram_backup_path/media.json");

    $combined_items = array();
    combine_dates($combined_items, $media->photos, false);
    combine_dates($combined_items, $media->videos, true);

    foreach($combined_items as $taken_at => $arr) {
        $ts    = strtotime($taken_at);
        $date  = date('Y-m-d H:i:s', $ts);
        $year  = date('Y', $ts);
        $month = date('m', $ts);
        $day   = date('d', $ts);

        // Create the WP upload directory...
        $dest_dir = "$wp_file_root/wp-content/uploads/$year/$month/$day";
        if(!file_exists($dest_dir)) {
            if(mkdir($dest_dir, 0777, true) === false) {
                die("Could not create directory: $dest_dir");
            }
        }

        // For lack of a better option, we'll make the new WordPress post's title the FB post's date
        $post_title   = date('F j, Y g:ia', $ts);

        // Clear out the post data on each loop through...
        $post_content = '';

        foreach($arr as $item) {
            $data = $item['data'];

            $caption  = @$data->caption;
            $location = @$data->location;

            // Figure out where everything's gonna go...
            $path     = $instagram_backup_path . '/' . $data->path;
            $img_path = DOC_ROOT . "/$path";
            $fn       = basename($img_path);
            $dest_fn  = "$dest_dir/$fn";

            // And copy the media into WP uploads...
            if(copy($path, $dest_fn) === false) {
                die("Could not copy $path to $dest_fn");
            }

            // Build the media item's URL
            $item_url = "$wp_web_root/wp-content/uploads/$year/$month/$day/$fn";

            // Add the media item into the post...
            if($item['video']) {
                $post_content .= "<p><video controls><source src='$item_url' type='video/mp4'></video></p>";
            } else {
                $post_content .= "<p><img src='$item_url' /></p>";
            }

            // If the item has a description, handle it...
            if(!empty($caption)) {
                $caption = replace_bad_words($caption);
                $post_content .= DoMarkdown($caption);
            }

            // If the item has a location and we're cool with importing locations...
            if(!empty($location) && ($import_locations === true)) {
                $post_content .= "<p><small>$location</small></p>";
            }
        }

        // Insert the new post into WordPress...
        $wp_post_author_id = intval($wp_post_author_id);
        $post_content      = mysqli_real_escape_string($db, $post_content);
        $post_title        = mysqli_real_escape_string($db, $post_title);
        $wp_comment_status = mysqli_real_escape_string($db, $wp_comment_status);
        $wp_ping_status    = mysqli_real_escape_string($db, $wp_ping_status);

        $sql = 'INSERT INTO wp_posts (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_modified, post_modified_gmt, post_parent, post_type, comment_count, to_ping, pinged, post_content_filtered) ';
        $sql .= "VALUES ($wp_post_author_id, '$date', '$date', '$post_content', '$post_title', '', 'publish', '$wp_comment_status', '$wp_ping_status', '$date', '$date', 0, 'post', 0, '', '', '')";
        mysqli_query($db, $sql) or die('MySQL error: ' . mysqli_error($db));
        $post_id = mysqli_insert_id($db);

        $post_name = $post_id;
        $guid = "$wp_web_root/?p=$post_id";

        $sql = "UPDATE wp_posts SET post_name = '$post_name', guid = '$guid' WHERE ID = $post_id";
        mysqli_query($db, $sql) or die('MySQL error: ' . mysqli_error($db));

        echo "Imported Instagram post from $date<br>\n";
    }

    // Replace any words you don't want to appear in the post body.
    // Example: Your kids' full names.
    function replace_bad_words($str) {
        global $replacements;

        foreach($replacements as $needle => $sub) {
            $str = str_replace($needle, "<strong>$sub</strong>", $str);
        }

        return $str;
    }

    // In my testing with my own data, Instagram posts that contained multiple items would often have each item's
    // timestamp within a few seconds of each other - not identical despite belonging to the same post.
    // This function strips off the seconds of each timestamp and combines them together under the assumption
    // that items posted at the same minute should belong in a single WordPress post together.
    function combine_dates(&$combined_items, $array, $video) {
        foreach($array as $data) {
            $ts = strtotime($data->taken_at);
            $key = date('Y-m-d H:i:00', $ts); // We create a key based on the date without seconds, because I ran into a few multi-Instagram posts that had slightly different seconds values on the same post.
            if(!isset($combined_items[$key])) {
                $combined_items[$key] = array();
            }
            $combined_items[$key][] = array('video' => $video, 'data' => $data);
        }
    }
