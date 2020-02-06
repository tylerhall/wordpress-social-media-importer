<?PHP
    require 'settings.inc.php';
    require 'markdown.inc.php';

    $posts = json_decode(file_get_contents($fb_posts_json));
    if($posts === false) die("Could not open and/or parse $fb_posts_json");

    foreach($posts as $post) {
        $ts    = $post->timestamp;
        $date  = date('Y-m-d H:i:s', $ts);
        $year  = date('Y', $ts);
        $month = date('m', $ts);
        $day   = date('d', $ts);

        // Check if the $post has at least one photo or video attached...
        $has_media = false;
        if(isset($post->attachments) && (count($post->attachments) > 0)) {
            foreach($post->attachments as $a) {
                if(isset($a->data) && (count($a->data) > 0)) {
                    foreach($a->data as $data) {
                        if(isset($data->media)) {
                            $has_media = true;
                        }
                    }
                }
            }
        }
        if(!$has_media) {
            continue;
        }

        // For lack of a better option, we'll make the new WordPress post's title the FB post's date
        $post_title   = date('F j, Y g:ia', $ts);

        // Often times, the text content fo the FB post is duplicated by the caption of one of the
        // attached photos or videos. So, let's keep track of all the media descriptions we find
        // so we can avoid adding a duplicate at the end.
        $descriptions = array();

        // Grab the text content of the post if there is any...
        $post_embedded_title = null;
        if(isset($post->data)) {
            foreach($post->data as $data) {
                if(isset($data->post)) {
                    $post_embedded_title = replace_people_ids($data->post);
                }
            }
        }

        // Clear out the post data on each loop through...
        $post_content = '';
        $place = null;

        foreach($post->attachments as $a) {
            foreach($a->data as $data) {
                if(isset($data->media)) {
                    // Create the WP upload directory...
                    $dest_dir = "$wp_file_root/wp-content/uploads/$year/$month/$day";
                    if(!file_exists($dest_dir)) {
                        if(mkdir($dest_dir, 0777, true) === false) {
                            die("Could not create directory: $dest_dir");
                        }
                    }

                    // Figure out where everything's gonna go...
                    $path     = $fb_backup_path . '/' . $data->media->uri;
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
                    $is_video = isset($data->media->media_metadata->video_metadata);
                    if($is_video) {
                        $post_content .= "<p><video controls><source src='$item_url' type='video/mp4'></video></p>";
                    } else {
                        $post_content .= "<p><img src='$item_url' /></p>";
                    }

                    // If the item has a description, handle it...
                    if(isset($data->media->description)) {
                        $str = replace_bad_words($data->media->description);
                        $str = replace_people_ids($str);
                        $str = DoMarkdown($str);
                        $str = utf8_decode($str);
                        $post_content .= $str;
                        $descriptions[] = strip_tags(replace_people_ids($data->media->description));
                    }
                }

                // If the item has a location and we're cool with importing locations...
                if(isset($data->place) && ($import_locations === true)) {
                    $place = $data->place->name;
                }
            }
        }

        // If we have a caption/title/description/whatever for the post itself, handle that
        // but be sure not to duplicate an attachment's caption...
        if(isset($post_embedded_title)) {
            if(!in_array($post_embedded_title, $descriptions)) {
                $str = replace_bad_words($post_embedded_title);
                $str = replace_people_ids($str);
                $str = DoMarkdown($str);
                $str = utf8_decode($str);
                $post_content .= $str;
            }
        }

        // If there is a location and we're cool with importing locations...
        if(isset($place) && ($import_locations === true)) {
            $post_content .= "<p><small>$place</small></p>";
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

        echo "Imported Facebook post from $date<br>\n";
    }

    // This cleans up Facebook's "tagged" person names and bolds them...
    function replace_people_ids($str) {
        return preg_replace('/@\[[0-9]+:[0-9]+:(.*?)\]/', '<strong>$1</strong>', $str);
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
