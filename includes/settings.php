<?php


// array sort function modified from
// https://www.php.net/manual/en/function.sort.php
// contributed function by phpdotnet at m4tt dot co dot uk ¶
//
function array_sort($array, $on, $order=SORT_ASC)
{
    $new_array = array();
    $sortable_array = array();

    if (count($array) > 0) {
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2) {
                    if ($k2 == $on) {
                        $sortable_array[$k] = $v2;
                    }
                }
            } else {
                $sortable_array[$k] = $v;
            }
        }

        switch ($order) {
            case SORT_ASC:
                natsort($sortable_array);
                break;
            case SORT_DESC:
                arsort($sortable_array);
                break;
        }

        foreach ($sortable_array as $k => $v) {
            $new_array[$k] = $array[$k];
        }
    }

    return $new_array;
}


// Downloads the remote image for use locally
function download_image($image_url) {
    return media_sideload_image($image_url, 0, "image", 'src');
}



// Create a post from the facebook post (including images and descriptions)
function create_post($post_id, $facebook_key, &$wpfpg_show_message, &$created_posts, $wpfpg_new_post_type, $wpfpg_new_post_status, $wpfpg_new_post_author, $category_id) {
    // downloads the post data using the facebook Graph API
    $response = wp_remote_get('https://graph.facebook.com/v14.0/' . $post_id
        . '?fields=attachments,full_picture,message,created_time&access_token='
        . $facebook_key);

    // make sure it worked ok
    $http_code = wp_remote_retrieve_response_code($response);

    // if it is ok
    if ($http_code == 200) {

        // retrieve the body
        $body = wp_remote_retrieve_body($response);

        // decode it as an array
        $decoded = json_decode($body, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

        // get the message part
        $message = $decoded['message'];

        // the title will be the date
        $post_title = date("d F Y", strtotime($decoded['created_time']));

        // compute the next day
        $date_next = date("d F Y", strtotime($decoded['created_time'])+24*3600);

        $nb_photo = 0;
        $photo_id = 0;
        $message_att = '';

        // for each attached image
        foreach ($decoded['attachments']['data'][0]['subattachments']['data'] as $att) {
            //print_r($att);
            // get the URL of the image
            $img = $att['media']['image'];

            // download it locally
            $src = download_image($img['src']);

            if($src) {
                // <figure class="wp-block-image size-large"><img loading="lazy" width="1024" height="768" src="https://i0.wp.com/mycovid19japannewsdigest.info/wp-content/uploads/2022/05/01-1.jpg?resize=1024%2C768&#038;ssl=1" alt="" class="wp-image-92" srcset="https://i0.wp.com/mycovid19japannewsdigest.info/wp-content/uploads/2022/05/01-1-scaled.jpg?resize=1024%2C768&amp;ssl=1 1024w, https://i0.wp.com/mycovid19japannewsdigest.info/wp-content/uploads/2022/05/01-1-scaled.jpg?resize=300%2C225&amp;ssl=1 300w, https://i0.wp.com/mycovid19japannewsdigest.info/wp-content/uploads/2022/05/01-1-scaled.jpg?resize=768%2C576&amp;ssl=1 768w, https://i0.wp.com/mycovid19japannewsdigest.info/wp-content/uploads/2022/05/01-1-scaled.jpg?resize=1536%2C1152&amp;ssl=1 1536w, https://i0.wp.com/mycovid19japannewsdigest.info/wp-content/uploads/2022/05/01-1-scaled.jpg?w=2000&amp;ssl=1 2000w" sizes="(max-width: 1000px) 100vw, 1000px" data-recalc-dims="1" /></figure>
                // create a link to the image
                $img_tag = "<figure class='wp-block-image size-large'><img loading='lazy' width='1024' src='" . $src . "'  class='wp-image-92' ></figure> <br>";

                // add the link and the image description
                $message_att .= '<div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div><p>'.
                    $img_tag .
                    $att['description'] . '<br></p>';
            }

            $photo_id = $att['target']['id'];

            $nb_photo++;
        }

        // we may miss some photos if we have more than 10
        if($nb_photo > 10) {
            $message = searchForMorePhotos($photo_id, $facebook_key, strtotime($decoded['created_time']), $message);
            // var_dump($message);
        } else {
            $message .= $message_att;
        }

        //var_dump($message);

        // prepare the post
        $post = array(
            'post_title' => wp_strip_all_tags($post_title),
            'post_content' => $message,
            'post_type' => $wpfpg_new_post_type,
            'post_status' => $wpfpg_new_post_status,
            'post_author' => $wpfpg_new_post_author,
            'post_category' => $category_id
        );

        // Insert the post into the database
        $post_id = wp_insert_post($post);

        // make sure it was inserted
        if (!is_wp_error($post_id)) {
            //the post is valid
            $wpfpg_show_message = 'success';

            // add its id to the post array
            $created_posts[] = $post_id;
        }

    } else {
        // we couldn't download the post, create an error message to show
        $wpfpg_show_message = 'Got this error code '.$http_code.' response '.$response;
    }
}




/**
 * search for more photos in the album of one of the attached photos
 * @param int $photo_id id of one of the attached photos
 * @param $facebook_key access token
 * @param $timestamp post date as a timestamp
 * @param string $message the original message
 * @return string the modified message
 */
function searchForMorePhotos(string $photo_id, string $facebook_key, int $timestamp, string $message): string
{
    // search for more
    $photo_request = 'https://graph.facebook.com/v14.0/' . $photo_id
        . '?fields=album&access_token='
        . $facebook_key;

    $pluginlog = plugin_dir_path(__FILE__).'debug.log';
    $log_message = 'First photo request : '.$photo_request.PHP_EOL;
    error_log($log_message, 3, $pluginlog);

    $response = wp_remote_get($photo_request);
    $try = 0;
    while(is_wp_error($response)) {
        $try++;
        if($try>5) {
            error_log( print_r($response, TRUE) , 3, $pluginlog);
            return $message;
        }
        $response = wp_remote_get($photo_request);
    }

    // make sure it worked ok
    $http_code = wp_remote_retrieve_response_code($response);

    $log_message = "First photo response code : $http_code".PHP_EOL;
    error_log($log_message, 3, $pluginlog);

    error_log( print_r($response, TRUE) , 3, $pluginlog);

    // if it is ok
    if ($http_code == 200) {
        // retrieve the body
        $body = wp_remote_retrieve_body($response);

        // decode it as an array
        $decoded = json_decode($body, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

        // print_r($decoded);

        $album_id = $decoded['album']['id'];

        $date_from_string = (string) $timestamp-24*3600;

        $date_to_string = (string) $timestamp+24*3600;

        $time_interval = "since=" . $date_from_string . "&until=" . $date_to_string . "&limit=100";

        $album_request = 'https://graph.facebook.com/v14.0/' . $album_id
            . '/photos?fields=images,name,created_time&' . $time_interval . '&access_token='
            . $facebook_key;

        $response = wp_remote_get($album_request);

        $try = 0;
        while(is_wp_error($response)) {
            $try++;
            if($try>5) {
                error_log( print_r($response, TRUE) , 3, $pluginlog);
                return $message;
            }
            $response = wp_remote_get($album_request);
        }

        // make sure it worked ok
        $http_code = wp_remote_retrieve_response_code($response);

        $log_message = 'First album request : '.$album_request.PHP_EOL;
        error_log($log_message, 3, $pluginlog);

        $log_message = "First album response code : $http_code".PHP_EOL;
        error_log($log_message, 3, $pluginlog);

        error_log( print_r($response, TRUE) , 3, $pluginlog);

        // if it is ok
        if ($http_code == 200) {
            // retrieve the body
            $body = wp_remote_retrieve_body($response);

            // decode it as an array
            $decoded = json_decode($body, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

            // print_r($decoded);

            $all_pictures = array();

            $nb_photo = 0;
            $nb_photo_kept = 0;

            foreach ($decoded['data'] as $image) {
                //print_r($att);
                $img = $image['images'][0];
                $img['name'] = $image['name'];

                $img_time = strtotime(($image['created_time']));

                if(abs($img_time - $timestamp) < 120) {
                    $all_pictures[] = $img;
                    $nb_photo_kept++;
                }
                $nb_photo++;
            }

            $log_message = "Got $nb_photo and kept $nb_photo_kept".PHP_EOL;
            error_log($log_message, 3, $pluginlog);

            while ($nb_photo == 100) {
                // try to get more
                $album_request = 'https://graph.facebook.com/v14.0/' . $album_id
                    . '/photos?fields=images,name,created_time&' . $time_interval
                    . '&after=' . $decoded['paging']['cursors']['after']
                    . '&access_token=' . $facebook_key;

                $response = wp_remote_get($album_request);

                $try = 0;
                while(is_wp_error($response)) {
                    $try++;
                    if($try>5) {
                        error_log( print_r($response, TRUE) , 3, $pluginlog);
                        return $message;
                    }
                    $response = wp_remote_get($album_request);
                }

                // make sure it worked ok
                $http_code = wp_remote_retrieve_response_code($response);

                $log_message = 'Next album request : '.$album_request.PHP_EOL;
                error_log($log_message, 3, $pluginlog);

                $log_message = "Next album response code : $http_code".PHP_EOL;
                error_log($log_message, 3, $pluginlog);

                error_log( print_r($response, TRUE) , 3, $pluginlog);

                $nb_photo = 0;
                $nb_photo_kept = 0;

                // if it is ok
                if ($http_code == 200) {
                    // retrieve the body
                    $body = wp_remote_retrieve_body($response);

                    $log_message = ' Next album response body : '.$body.PHP_EOL;
                    error_log($log_message, 3, $pluginlog);

                    // decode it as an array
                    $decoded = json_decode($body, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

                    foreach ($decoded['data'] as $image) {
                        //print_r($att);
                        $img = $image['images'][0];
                        $img['name'] = $image['name'];

                        $img_time = strtotime(($image['created_time']));

                        if(abs($img_time - $timestamp) < 120) {
                            $all_pictures[] = $img;
                            $nb_photo_kept++;
                        }
                        $nb_photo++;
                    }

                    $log_message = "Next Got $nb_photo and kept $nb_photo_kept".PHP_EOL;
                    error_log($log_message, 3, $pluginlog);
                }
            }

            $all_pictures = array_sort($all_pictures, 'name');

            foreach ($all_pictures as $value) {
                $img_tag = "<figure class='wp-block-image size-large'><img loading='lazy' width='1024' src='" . $value['source'] . "'  class='wp-image-92' ></figure> <br>";

                //$img_tag = "<img loading='lazy' width='1024' height='768' src='" . $value['source'] . "' alt='facebook image'> <br>";

                $message .= '<div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div><p>'.
                    $img_tag .
                    $value['name'] . '<br></p>';
            }
        }
    }
    return $message;
}



// create all the posts from a part of the facebook feed using Facebook Graph API
function create_post_from_feed_page($request, $access_token, $filter, &$wpfpg_show_message, &$created_posts, $wpfpg_new_post_type, $wpfpg_new_post_status, $wpfpg_new_post_author, $category_id) {
    // get the data from the feed
    $response = wp_remote_get($request);

    // make sure it worked
    $http_code = wp_remote_retrieve_response_code($response);

    // if it worked ok
    if ($http_code == 200) {
        // retrieve the body of the response
        $body = wp_remote_retrieve_body($response);

        // decode it as an array
        $decoded_feed = json_decode($body, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

        // loop on posts
        foreach ($decoded_feed['data'] as $post) {
            // if the posts satisfies the filter
            if (stristr($post['message'], $filter)) {
                // recreate the post here
                create_post($post['id'], $access_token, $wpfpg_show_message, $created_posts, $wpfpg_new_post_type, $wpfpg_new_post_status, $wpfpg_new_post_author, $category_id);
            }
        }
    } else {
        // we couldn't download from the feed, create an error message to show
        $wpfpg_show_message = 'Got this error code '.$http_code.' response '.$response;
    }
}

// manage what got posted from the form
function manage_post(&$created_posts, &$wpfpg_message, &$wpfpg_show_message)
{
    // make sure it's from our form
    if (isset($_POST['wpfpg_submit_for_create_posts'])) {
        // only manage the form once
        if (
            !isset($_POST['wpfpg_nonce'])
            || !wp_verify_nonce($_POST['wpfpg_nonce'], 'wpfpg_create_posts')
        ) {
            print 'Sorry, your nonce did not verify.';
            exit;
        } else {
            // extract all the form fields
            extract($_POST);

            // make sure we have at least the facebook key
            if (!empty($wpfpg_facebook_key)) {
                // copy the key
                $facebook_key = $wpfpg_facebook_key;

                // create an array for future posts categories
                $category_id = array(1);

                // get all the checked categories
                if (!empty($wpfpg_new_post_category)) {
                    foreach ($wpfpg_new_post_category as $id) {
                        if (!in_array($id, $category_id)) {
                            $category_id[] = $id;
                        }
                    }
                }

                // create an array for post ids
                $created_posts = array();

                // replace spaces with + in the dates strings
                $wpfpg_date_since = preg_replace('/\s+/', '+', $wpfpg_date_since);
                $wpfpg_date_until = preg_replace('/\s+/', '+', $wpfpg_date_until);
                // create the filtering part of the request
                $time_interval= "since=".$wpfpg_date_since."&until=".$wpfpg_date_until."&limit=".$wpfpg_max_posts;
                // create the request to the Graph API
                $first_req = "https://graph.facebook.com/v14.0/me/feed?".$time_interval."&access_token=".$facebook_key;

                // starts the post recreation with this request
                create_post_from_feed_page($first_req, $facebook_key, $wpfpg_filter, $wpfpg_show_message, $created_posts, $wpfpg_new_post_type, $wpfpg_new_post_status, $wpfpg_new_post_author, $category_id);

                // if we got no error
                if ($wpfpg_show_message && $wpfpg_show_message == 'success') {
                    $wpfpg_message = 'Posts Successfully Created!';
                } else {
                    // we got an error message
                    $wpfpg_message = $wpfpg_show_message;
                }
            }
        }
    }
}




