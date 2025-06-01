<?php
/**
 * Single Policy Template - Clean Version Without Author/Date
 * File: templates/single-policy.php
 */

get_header(); ?>

<main id="primary" class="site-main">

    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

            <!-- Policy Title -->
            <h1 class="entry-title"><?php the_title(); ?></h1>
            
            <?php
            // Generate PDF URL using plugin's PDF generation system
            $pdf_url = add_query_arg([
                'generate_pdf' => '1',
                'policy_pdf' => get_the_ID()
            ], home_url('/'));
            ?>

            <!-- Container for Metadata and PDF Download -->
            <div class="policy-header-container">

                <!-- Custom Fields and Taxonomy (Left) -->
                <div class="policy-metadata">
                    <?php
                    // Section (taxonomy)
                    $terms = get_the_terms(get_the_ID(), 'policy-section');
                    if ($terms && !is_wp_error($terms)) {
                        $term_names = wp_list_pluck($terms, 'name');
                        echo '<div><strong>Section:</strong> ' . esc_html(implode(', ', $term_names)) . '</div>';
                    }

                    // Custom Fields
                    $title_policy = get_field('title-policy');
                    $code_policy = get_field('code-policy');
                    $adopted_policy = get_field('adopted-policy');
                    $last_revised_policy = get_field('last_revised-policy');

                    if ($title_policy) {
                        echo '<div><strong>Title:</strong> ' . esc_html($title_policy) . '</div>';
                    }
                    if ($code_policy) {
                        echo '<div><strong>Code:</strong> ' . esc_html($code_policy) . '</div>';
                    }
                    if ($adopted_policy) {
                        echo '<div><strong>Adopted:</strong> ' . esc_html($adopted_policy) . '</div>';
                    }
                    if ($last_revised_policy) {
                        echo '<div><strong>Last Revised:</strong> ' . esc_html($last_revised_policy) . '</div>';
                    }
                    ?>
                </div>

                <!-- PDF Download (Right) -->
                <?php
                // Only show PDF download if the policy has required fields and DOMPDF is available
                $code = get_field('code-policy');
                $title_policy = get_field('title-policy');
                
                if (!empty($code) && !empty($title_policy)) {
                    // Check if DOMPDF is available
                    $dompdf_paths = [
                        plugin_dir_path(__FILE__) . '../includes/dompdf/autoload.inc.php',
                        plugin_dir_path(__FILE__) . '../vendor/dompdf/autoload.inc.php'
                    ];
                    
                    $dompdf_available = false;
                    foreach ($dompdf_paths as $path) {
                        if (file_exists($path)) {
                            $dompdf_available = true;
                            break;
                        }
                    }
                    
                    if ($dompdf_available) {
                        // Try to get PDF icon from various locations
                        $icon_sources = [
                            wp_upload_dir()['baseurl'] . '/pdf_download_icon.png',
                            get_stylesheet_directory_uri() . '/images/pdf_download_icon.png',
                            get_template_directory_uri() . '/images/pdf_download_icon.png',
                            plugin_dir_url(__FILE__) . '../assets/images/pdf-icon.png'
                        ];
                        
                        $icon_url = '';
                        foreach ($icon_sources as $url) {
                            if (strpos($url, home_url()) === 0) {
                                $local_path = str_replace(home_url('/'), ABSPATH, $url);
                                if (file_exists($local_path)) {
                                    $icon_url = $url;
                                    break;
                                }
                            }
                        }
                        
                        // If no icon found, create a simple SVG fallback
                        if (empty($icon_url)) {
                            $icon_url = 'data:image/svg+xml;base64,' . base64_encode('
                                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="6" y="4" width="20" height="24" rx="2" fill="#D63638"/>
                                    <text x="16" y="20" font-family="Arial" font-size="10" font-weight="bold" fill="white" text-anchor="middle">PDF</text>
                                </svg>
                            ');
                        }
                        
                        if ($icon_url) {
                            ?>
                            <div class="pdf-download-link">
                                <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" title="Download this policy as PDF">
                                    <img src="<?php echo esc_url($icon_url); ?>"
                                         alt="Download PDF"
                                         style="height: 32px; width: auto;">
                                </a>
                            </div>
                            <?php
                        }
                    }
                }
                ?>

            </div>

            <!-- Main Content -->
            <div class="entry-content">
                <?php 
                // Get content from ACF policy field with enhanced loading
                $policy_content = get_field('policy');
                
                // Enhanced content retrieval with fallbacks
                if (empty($policy_content)) {
                    // Try direct meta query
                    $policy_content = get_post_meta(get_the_ID(), 'policy', true);
                }
                
                if (empty($policy_content)) {
                    // Try underscore-prefixed meta (ACF internal)
                    $policy_content = get_post_meta(get_the_ID(), '_policy', true);
                }
                
                if (empty($policy_content)) {
                    // Last resort - use post content
                    $policy_content = get_the_content();
                }
                
                if ($policy_content) {
                    // Apply search highlighting if highlight parameter exists
                    if (!empty($_GET['highlight'])) {
                        $search_term = sanitize_text_field($_GET['highlight']);
                        $search_term = wp_strip_all_tags($search_term);
                        $search_term = preg_quote($search_term, '/');
                        
                        // Highlight the term (case-insensitive)
                        $policy_content = preg_replace(
                            '/(' . $search_term . ')/i',
                            '<mark class="search-highlight">$1</mark>',
                            $policy_content
                        );
                    }
                    
                    // Force proper paragraph formatting like the editor
                    echo wpautop($policy_content);
                } else {
                    echo '<p><em>No policy content available.</em></p>';
                }
                ?>
            </div>

            <!-- Enhanced Policy CSS -->
            <style>
            /* Policy header layout */
            .policy-header-container {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 20px;
                gap: 20px;
            }
            
            /* Policy metadata styling */
            .policy-metadata {
                flex: 1;
                margin-bottom: 1em;
                font-size: 1em;
            }

            .policy-metadata div {
                margin-bottom: 0.5em;
            }

            .policy-metadata strong {
                color: #0073aa;
            }
            
            /* PDF download link */
            .pdf-download-link {
                flex-shrink: 0;
                text-align: right;
            }

            .pdf-download-link a {
                display: inline-block;
                transition: transform 0.2s ease;
            }

            .pdf-download-link a:hover {
                transform: scale(1.1);
            }

            .pdf-download-link img {
                height: 32px;
                width: auto;
                filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
            }

            /* Policy content styling */
            .entry-content {
                line-height: 1.6;
            }

            /* Ensure proper spacing like the Classic Editor */
            .entry-content p {
                margin-bottom: 1em;
                line-height: 1.6;
            }
            
            .entry-content p:last-child {
                margin-bottom: 0;
            }
            
            /* Make spans behave properly within paragraphs */
            .entry-content span {
                display: inline;
                margin-right: 0.5em;
            }
            
            .entry-content span + span {
                margin-left: 0.25em;
            }
            
            /* Ensure spans in paragraphs have proper spacing */
            .entry-content p span {
                margin-right: 0.5em;
            }
            
            .entry-content p span:last-child {
                margin-right: 0;
            }

            /* Policy tables styling */
            .entry-content table {
                width: 100%;
                border-collapse: collapse;
                margin: 1em 0;
            }

            .entry-content table.black-bordered,
            .entry-content table[border="1"] {
                border-collapse: collapse;
            }

            .entry-content table.black-bordered th,
            .entry-content table.black-bordered td,
            .entry-content table[border="1"] th,
            .entry-content table[border="1"] td {
                border: 1px solid black;
                padding: 8px;
                text-align: left;
            }

            /* Policy list styling */
            .entry-content ol.policy-list[type="1"] { list-style-type: decimal; }
            .entry-content ol.policy-list[type="a"] { list-style-type: lower-alpha; }
            .entry-content ol.policy-list[type="A"] { list-style-type: upper-alpha; }
            .entry-content ol.policy-list[type="i"] { list-style-type: lower-roman; }
            .entry-content ol.policy-list[type="I"] { list-style-type: upper-roman; }

            .entry-content ol.policy-list {
                margin-left: 0;
                padding-left: 1.25em;
            }

            .entry-content .policy-list li {
                margin-bottom: 0.25em;
            }

            .entry-content .policy-list ol ol {
                margin-top: 0.25em;
                margin-bottom: 0.25em;
                padding-left: 1.25em;
            }
            
            /* Search highlighting */
            .search-highlight {
                background-color: #fe7fa5 !important;
                color: #000 !important;
                padding: 2px 4px !important;
                border-radius: 3px !important;
                font-weight: 600 !important;
            }
            
            mark.search-highlight {
                background-color: #fe7fa5 !important;
                color: #000 !important;
            }

            /* Mobile responsive */
            @media (max-width: 768px) {
                .policy-header-container {
                    flex-direction: column;
                    gap: 15px;
                }
                
                .pdf-download-link {
                    text-align: left;
                    order: -1;
                }
                
                .policy-metadata {
                    margin-bottom: 1.5em;
                }
                
                .entry-content table {
                    font-size: 0.9em;
                }
                
                .entry-content table th,
                .entry-content table td {
                    padding: 6px;
                }
                
                .entry-content span {
                    display: inline;
                    margin-right: 0.25em;
                }
            }
            
            /* ✅ HIDE AUTHOR AND DATE INFORMATION */
            .entry-meta,
            .posted-on,
            .byline,
            .author,
            .post-author,
            .entry-date,
            .posted-by,
            .entry-footer .cat-links,
            .entry-footer .tags-links,
            .entry-footer .edit-link,
            .entry-footer .comments-link {
                display: none !important;
            }
            
            /* Hide separators like "/" between author and date */
            .entry-meta .sep,
            .entry-meta-separator,
            .meta-sep {
                display: none !important;
            }
            
            /* Hide entire entry footer if it only contains meta info */
            .entry-footer:empty {
                display: none !important;
            }
            
            /* Hide breadcrumbs that might show date/author */
            .entry-header .entry-meta {
                display: none !important;
            }
            </style>

        </article>

    <?php endwhile; endif; ?>

</main>

<?php get_footer(); ?>