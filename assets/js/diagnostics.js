/**
 * MemberPress AI Assistant Diagnostics JavaScript
 */
(function($) {
    'use strict';

    /**
     * Diagnostic tools object
     */
    var MPAI_Diagnostics = {
        /**
         * Initialize
         */
        init: function() {
            // Initialize tabs
            this.initTabs();
            
            // Initialize accordions
            this.initAccordions();
            
            // Initialize test runners
            this.initTestRunners();
        },

        /**
         * Initialize tabs
         */
        initTabs: function() {
            // Only initialize our custom diagnostic tabs, not the WordPress admin tabs
            
            // Handle diagnostic category tabs
            $('.mpai-category-tabs a').on('click', function(e) {
                e.preventDefault();
                
                // Get the target tab
                var target = $(this).attr('href');
                
                // Remove active class from all tabs
                $('.mpai-category-tabs a').removeClass('active');
                
                // Hide all category content
                $('.mpai-category-content').hide();
                
                // Add active class to the clicked tab and show its content
                $(this).addClass('active');
                $(target).show();
            });
            
            // Activate the first category tab by default
            if ($('.mpai-category-tabs a:first').length) {
                $('.mpai-category-tabs a:first').click();
            }
        },

        /**
         * Initialize accordions
         */
        initAccordions: function() {
            // Handle accordion header clicks
            $(document).on('click', '.mpai-accordion-header', function() {
                $(this).toggleClass('active');
                var content = $(this).next('.mpai-accordion-content');
                
                if (content.css('maxHeight') !== '0px') {
                    content.css('maxHeight', '0px');
                } else {
                    content.css('maxHeight', content.prop('scrollHeight') + 'px');
                }
            });
        },

        /**
         * Initialize test runners
         */
        initTestRunners: function() {
            // Handle single test buttons
            // Remove any previous click handlers
            $('.mpai-run-test').off('click');
            $(document).on('click', '.mpai-run-test', function() {
                var $testCard = $(this).closest('.mpai-test-card');
                var testId = $testCard.data('test-id');
                var $resultContainer = $testCard.find('.mpai-test-result');
                var $statusText = $testCard.find('.mpai-status-text');
                var $statusDot = $testCard.find('.mpai-status-dot');
                
                // Update status
                $statusText.text('Running...');
                $statusDot.removeClass('mpai-status-unknown mpai-status-success mpai-status-error')
                          .addClass('mpai-status-running');
                
                // Show result container with loading message
                $resultContainer.html('<p>Running test, please wait...</p>').show();
                
                // Make AJAX request to run the test
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mpai_run_diagnostic_test',
                        test_id: testId,
                        nonce: mpai_diagnostics.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update status
                            $statusText.text(response.data.status === 'success' ? 'Passed' : 
                                             response.data.status === 'warning' ? 'Warning' : 'Failed');
                            $statusDot.removeClass('mpai-status-unknown mpai-status-running')
                                      .addClass('mpai-status-' + response.data.status);
                            
                            // Build result HTML
                            var resultHtml = '<div class="mpai-test-result-content">';
                            resultHtml += '<p class="mpai-test-message">' + response.data.message + '</p>';
                            
                            // Add details if available
                            if (response.data.details) {
                                resultHtml += '<div class="mpai-test-details">';
                                resultHtml += '<h4>Details:</h4>';
                                resultHtml += '<pre>' + JSON.stringify(response.data.details, null, 2) + '</pre>';
                                resultHtml += '</div>';
                            }
                            
                            resultHtml += '</div>';
                            $resultContainer.html(resultHtml);
                        } else {
                            // Show error
                            $statusText.text('Error');
                            $statusDot.removeClass('mpai-status-unknown mpai-status-running')
                                      .addClass('mpai-status-error');
                            $resultContainer.html('<p class="mpai-error">Error: ' + 
                                                (response.data ? response.data.message : 'Unknown error') + '</p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        // Show error
                        $statusText.text('Error');
                        $statusDot.removeClass('mpai-status-unknown mpai-status-running')
                                  .addClass('mpai-status-error');
                        $resultContainer.html('<p class="mpai-error">AJAX Error: ' + error + '</p>');
                    }
                });
            });
            
            // Handle category test runner
            $(document).on('click', '.mpai-run-category-tests', function() {
                var categoryId = $(this).data('category');
                var $categoryContent = $('#category-' + categoryId);
                var $testCards = $categoryContent.find('.mpai-test-card');
                
                // Update all test cards in the category to "Running"
                $testCards.each(function() {
                    var $statusText = $(this).find('.mpai-status-text');
                    var $statusDot = $(this).find('.mpai-status-dot');
                    
                    $statusText.text('Queued...');
                    $statusDot.removeClass('mpai-status-unknown mpai-status-success mpai-status-error')
                              .addClass('mpai-status-unknown');
                });
                
                // Make AJAX request to run all tests in the category
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mpai_run_category_tests',
                        category: categoryId,
                        nonce: mpai_diagnostics.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update each test card with results
                            $.each(response.data.all_results, function(testId, result) {
                                var $testCard = $categoryContent.find('.mpai-test-card[data-test-id="' + testId + '"]');
                                var $statusText = $testCard.find('.mpai-status-text');
                                var $statusDot = $testCard.find('.mpai-status-dot');
                                var $resultContainer = $testCard.find('.mpai-test-result');
                                
                                // Update status
                                $statusText.text(result.status === 'success' ? 'Passed' : 
                                               result.status === 'warning' ? 'Warning' : 'Failed');
                                $statusDot.removeClass('mpai-status-unknown mpai-status-running')
                                          .addClass('mpai-status-' + result.status);
                                
                                // Build result HTML
                                var resultHtml = '<div class="mpai-test-result-content">';
                                resultHtml += '<p class="mpai-test-message">' + result.message + '</p>';
                                
                                // Add details if available
                                if (result.details) {
                                    resultHtml += '<div class="mpai-test-details">';
                                    resultHtml += '<h4>Details:</h4>';
                                    resultHtml += '<pre>' + JSON.stringify(result.details, null, 2) + '</pre>';
                                    resultHtml += '</div>';
                                }
                                
                                resultHtml += '</div>';
                                $resultContainer.html(resultHtml).show();
                            });
                        } else {
                            alert('Error running category tests: ' + 
                                 (response.data ? response.data.message : 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('AJAX Error: ' + error);
                    }
                });
            });
            
            // Handle global "Run All Tests" button
            $('#mpai-run-all-tests').on('click', function() {
                var $summaryContainer = $('#mpai-test-results-summary');
                var $summaryContent = $('#mpai-summary-content');
                
                $summaryContainer.show();
                $summaryContent.html('<p>Running all tests, please wait...</p>');
                
                // Update all test cards to "Queued"
                $('.mpai-test-card').each(function() {
                    var $statusText = $(this).find('.mpai-status-text');
                    var $statusDot = $(this).find('.mpai-status-dot');
                    
                    $statusText.text('Queued...');
                    $statusDot.removeClass('mpai-status-unknown mpai-status-success mpai-status-error')
                              .addClass('mpai-status-unknown');
                });
                
                // Make AJAX request to run all tests
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mpai_run_all_tests',
                        nonce: mpai_diagnostics.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update each test card with results
                            $.each(response.data.all_results, function(testId, result) {
                                var $testCard = $('.mpai-test-card[data-test-id="' + testId + '"]');
                                var $statusText = $testCard.find('.mpai-status-text');
                                var $statusDot = $testCard.find('.mpai-status-dot');
                                var $resultContainer = $testCard.find('.mpai-test-result');
                                
                                // Update status
                                $statusText.text(result.status === 'success' ? 'Passed' : 
                                               result.status === 'warning' ? 'Warning' : 'Failed');
                                $statusDot.removeClass('mpai-status-unknown mpai-status-running')
                                          .addClass('mpai-status-' + result.status);
                                
                                // Build result HTML
                                var resultHtml = '<div class="mpai-test-result-content">';
                                resultHtml += '<p class="mpai-test-message">' + result.message + '</p>';
                                
                                // Add details if available
                                if (result.details) {
                                    resultHtml += '<div class="mpai-test-details">';
                                    resultHtml += '<h4>Details:</h4>';
                                    resultHtml += '<pre>' + JSON.stringify(result.details, null, 2) + '</pre>';
                                    resultHtml += '</div>';
                                }
                                
                                resultHtml += '</div>';
                                $resultContainer.html(resultHtml).show();
                            });
                            
                            // Build summary HTML
                            var summaryHtml = '<div class="mpai-summary-table">';
                            summaryHtml += '<h4>Test Results by Category</h4>';
                            summaryHtml += '<table class="widefat">';
                            summaryHtml += '<thead><tr><th>Category</th><th>Passed</th><th>Failed</th><th>Warnings</th></tr></thead>';
                            summaryHtml += '<tbody>';
                            
                            $.each(response.data.grouped_results, function(category, results) {
                                summaryHtml += '<tr>';
                                summaryHtml += '<td>' + category + '</td>';
                                summaryHtml += '<td>' + results.success_count + '</td>';
                                summaryHtml += '<td>' + results.error_count + '</td>';
                                summaryHtml += '<td>' + results.warning_count + '</td>';
                                summaryHtml += '</tr>';
                            });
                            
                            summaryHtml += '</tbody></table></div>';
                            $summaryContent.html(summaryHtml);
                        } else {
                            $summaryContent.html('<p class="mpai-error">Error running all tests: ' + 
                                              (response.data ? response.data.message : 'Unknown error') + '</p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $summaryContent.html('<p class="mpai-error">AJAX Error: ' + error + '</p>');
                    }
                });
            });
            
            // Handle edge case test runner
            $('#mpai-run-edge-case-tests').on('click', function() {
                var $resultsContainer = $('#mpai-edge-case-results');
                var $outputContainer = $('#mpai-edge-case-output');
                
                $resultsContainer.show();
                $outputContainer.html('<p>Running edge case tests, please wait...</p>');
                
                // Make an AJAX request to run the edge case tests
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mpai_run_edge_case_tests',
                        nonce: mpai_diagnostics.nonce
                    },
                    success: function(response) {
                        try {
                            // Display the test results
                            if (response.success) {
                                $outputContainer.html(response.data);
                                
                                // Initialize accordions in the test results
                                MPAI_Diagnostics.initAccordions();
                            } else {
                                $outputContainer.html('<div class="notice notice-error"><p>Error running edge case tests: ' + 
                                    (response.data || 'Unknown error') + '</p></div>');
                            }
                        } catch (e) {
                            $outputContainer.html('<div class="notice notice-error"><p>Error processing test results: ' + e.message + '</p></div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $outputContainer.html('<div class="notice notice-error"><p>AJAX error: ' + error + '</p></div>');
                    }
                });
            });
            
            // Handle error recovery test runner
            $('#mpai-run-error-recovery-test').on('click', function() {
                var $resultsContainer = $('#mpai-error-recovery-results');
                var $outputContainer = $('#mpai-error-recovery-output');
                
                $resultsContainer.show();
                $outputContainer.html('<p>Running error recovery tests, please wait...</p>');
                
                // Make an AJAX request to run the error recovery tests
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mpai_test_error_recovery',
                        nonce: mpai_diagnostics.nonce
                    },
                    success: function(response) {
                        try {
                            // Display the test results
                            if (response.success) {
                                var html = '<h3>Error Recovery Test Results</h3>';
                                html += '<div class="mpai-test-summary">';
                                html += '<p><strong>Status:</strong> ' + (response.status === 'success' ? '<span class="tests-passed">Success</span>' : '<span class="tests-failed">Failed</span>') + '</p>';
                                html += '<p><strong>Message:</strong> ' + response.message + '</p>';
                                
                                // Add details if available
                                if (response.details) {
                                    html += '<h4>Details:</h4>';
                                    html += '<pre class="mpai-debug-output">' + JSON.stringify(response.details, null, 2) + '</pre>';
                                }
                                
                                html += '</div>';
                                $outputContainer.html(html);
                            } else {
                                $outputContainer.html('<div class="notice notice-error"><p>Error running error recovery tests: ' + 
                                    (response.message || 'Unknown error') + '</p></div>');
                            }
                        } catch (e) {
                            $outputContainer.html('<div class="notice notice-error"><p>Error processing test results: ' + e.message + '</p></div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $outputContainer.html('<div class="notice notice-error"><p>AJAX error: ' + error + '</p></div>');
                    }
                });
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        MPAI_Diagnostics.init();
    });

})(jQuery);