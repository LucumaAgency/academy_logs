jQuery(document).ready(function($) {
    
    // Modal handling
    var modal = $('#cpm-modal');
    var modalTitle = $('#cpm-modal-title');
    var modalBody = $('#cpm-modal-body');
    
    $('.cpm-close').on('click', function() {
        modal.hide();
    });
    
    $(window).on('click', function(e) {
        if ($(e.target).is(modal)) {
            modal.hide();
        }
    });
    
    // Quick Edit functionality
    $('.cpm-quick-edit').on('click', function(e) {
        e.preventDefault();
        var courseId = $(this).data('course-id');
        
        modalTitle.text('Quick Edit Relationships');
        modalBody.html('<p>Loading...</p>');
        modal.show();
        
        $.ajax({
            url: cpm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cpm_get_relationship_details',
                nonce: cpm_ajax.nonce,
                course_id: courseId
            },
            success: function(response) {
                if (response.success) {
                    renderQuickEditForm(response.data);
                } else {
                    modalBody.html('<p class="error">Error loading details: ' + response.data + '</p>');
                }
            },
            error: function() {
                modalBody.html('<p class="error">Failed to load relationship details.</p>');
            }
        });
    });
    
    function renderQuickEditForm(data) {
        var html = '<div class="cpm-quick-edit-form">';
        html += '<h3>Course: ' + data.course.title + '</h3>';
        html += '<table>';
        
        // Course Product
        html += '<tr><td><label>Course Product:</label>';
        if (data.course_product) {
            html += '<p>' + data.course_product.title + ' (ID: ' + data.course_product.id + ')</p>';
            html += '<p>Price: $' + data.course_product.price + '</p>';
            html += '<a href="' + data.course_product.edit_link + '" target="_blank" class="button">Edit Product</a> ';
            html += '<button class="button cpm-remove-inline" data-type="course" data-course-id="' + data.course.id + '">Remove</button>';
        } else {
            html += '<p class="no-product">No product linked</p>';
            html += '<button class="button cpm-add-inline" data-type="course" data-course-id="' + data.course.id + '">Add Product</button>';
        }
        html += '</td></tr>';
        
        // Webinar Product
        html += '<tr><td><label>Webinar Product:</label>';
        if (data.webinar_product) {
            html += '<p>' + data.webinar_product.title + ' (ID: ' + data.webinar_product.id + ')</p>';
            html += '<p>Price: $' + data.webinar_product.price + '</p>';
            html += '<a href="' + data.webinar_product.edit_link + '" target="_blank" class="button">Edit Product</a> ';
            html += '<button class="button cpm-remove-inline" data-type="webinar" data-course-id="' + data.course.id + '">Remove</button>';
        } else {
            html += '<p class="no-product">No product linked</p>';
            html += '<button class="button cpm-add-inline" data-type="webinar" data-course-id="' + data.course.id + '">Add Product</button>';
        }
        html += '</td></tr>';
        
        // Course Page
        if (data.course_page) {
            html += '<tr><td><label>Course Page:</label>';
            html += '<p>' + data.course_page.title + ' (ID: ' + data.course_page.id + ')</p>';
            html += '<a href="' + data.course_page.edit_link + '" target="_blank" class="button">Edit Page</a>';
            html += '</td></tr>';
        }
        
        html += '</table>';
        html += '<div class="cpm-actions">';
        html += '<button class="button cpm-close-modal">Close</button>';
        html += '</div>';
        html += '</div>';
        
        modalBody.html(html);
        
        // Bind events for dynamically added elements
        $('.cpm-close-modal').on('click', function() {
            modal.hide();
        });
        
        $('.cpm-remove-inline').on('click', function(e) {
            e.preventDefault();
            removeProduct($(this).data('course-id'), $(this).data('type'));
        });
        
        $('.cpm-add-inline').on('click', function(e) {
            e.preventDefault();
            showProductSelector($(this).data('course-id'), $(this).data('type'));
        });
    }
    
    // Add Product functionality
    $('.cpm-add-product').on('click', function(e) {
        e.preventDefault();
        var courseId = $(this).data('course-id');
        var type = $(this).data('type');
        showProductSelector(courseId, type);
    });
    
    function showProductSelector(courseId, type) {
        modalTitle.text('Select ' + (type === 'webinar' ? 'Webinar' : 'Course') + ' Product');
        modalBody.html('<p>Loading products...</p>');
        modal.show();
        
        $.ajax({
            url: cpm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cpm_get_products',
                nonce: cpm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderProductSelector(response.data, courseId, type);
                } else {
                    modalBody.html('<p class="error">Error loading products.</p>');
                }
            }
        });
    }
    
    function renderProductSelector(products, courseId, type) {
        var html = '<div class="cpm-product-selector">';
        html += '<input type="text" id="product-search" placeholder="Search products...">';
        
        html += '<div class="cpm-create-new-product">';
        html += '<h4>Create New Product</h4>';
        html += '<input type="text" id="new-product-title" placeholder="Product Title">';
        html += '<input type="number" id="new-product-price" placeholder="Price" step="0.01" min="0">';
        html += '<button class="button button-primary" id="create-new-product">Create & Link</button>';
        html += '</div>';
        
        html += '<h4>Or Select Existing Product:</h4>';
        html += '<ul class="cpm-product-list">';
        
        products.forEach(function(product) {
            var disabled = product.related_course && product.related_course != courseId;
            html += '<li class="cpm-product-item' + (disabled ? ' disabled' : '') + '" data-product-id="' + product.id + '">';
            html += '<strong>' + product.title + '</strong>';
            html += '<div class="product-meta">ID: ' + product.id + ' | Price: $' + product.price;
            if (disabled) {
                html += ' | Already linked to course #' + product.related_course;
            }
            html += '</div>';
            html += '</li>';
        });
        
        html += '</ul>';
        html += '<div class="cpm-actions">';
        html += '<button class="button cpm-close-modal">Cancel</button>';
        html += '</div>';
        html += '</div>';
        
        modalBody.html(html);
        
        // Search functionality
        $('#product-search').on('keyup', function() {
            var searchTerm = $(this).val().toLowerCase();
            $('.cpm-product-item').each(function() {
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(searchTerm) > -1);
            });
        });
        
        // Select product
        $('.cpm-product-item:not(.disabled)').on('click', function() {
            var productId = $(this).data('product-id');
            linkProduct(courseId, type, productId);
        });
        
        // Create new product
        $('#create-new-product').on('click', function() {
            var title = $('#new-product-title').val();
            var price = $('#new-product-price').val();
            
            if (!title) {
                alert('Please enter a product title');
                return;
            }
            
            linkProduct(courseId, type, 'new', title, price);
        });
        
        $('.cpm-close-modal').on('click', function() {
            modal.hide();
        });
    }
    
    function linkProduct(courseId, type, productId, newTitle, newPrice) {
        var data = {
            action: 'cpm_save_relationship',
            nonce: cpm_ajax.nonce,
            course_id: courseId,
            product_type: type,
            product_id: productId
        };
        
        if (productId === 'new') {
            data.new_course_product_title = newTitle;
            data.new_course_product_price = newPrice;
            
            if (type === 'webinar') {
                data.new_webinar_product_title = newTitle;
                data.new_webinar_product_price = newPrice;
            }
            
            if (type === 'course') {
                data.course_product_id = 'new';
            } else {
                data.webinar_product_id = 'new';
            }
        }
        
        modalBody.html('<p>Saving...</p>');
        
        $.ajax({
            url: cpm_ajax.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    modal.hide();
                    location.reload();
                } else {
                    modalBody.html('<p class="error">Error: ' + response.data + '</p>');
                }
            },
            error: function() {
                modalBody.html('<p class="error">Failed to save relationship.</p>');
            }
        });
    }
    
    // Remove Product functionality
    $('.cpm-remove-product').on('click', function(e) {
        e.preventDefault();
        var courseId = $(this).data('course-id');
        var type = $(this).data('type');
        
        if (confirm('Are you sure you want to remove this product relationship?')) {
            removeProduct(courseId, type);
        }
    });
    
    function removeProduct(courseId, type) {
        $.ajax({
            url: cpm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cpm_delete_relationship',
                nonce: cpm_ajax.nonce,
                course_id: courseId,
                product_type: type
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Failed to remove relationship.');
            }
        });
    }
    
    // Add New Relationship Form
    $('#product-type').on('change', function() {
        var type = $(this).val();
        
        $('#course-product-row, #webinar-product-row').hide();
        
        if (type === 'course' || type === 'both') {
            $('#course-product-row').show();
        }
        
        if (type === 'webinar' || type === 'both') {
            $('#webinar-product-row').show();
        }
    });
    
    $('#course-product').on('change', function() {
        if ($(this).val() === 'new') {
            $('#new-course-product-fields').show();
        } else {
            $('#new-course-product-fields').hide();
        }
    });
    
    $('#webinar-product').on('change', function() {
        if ($(this).val() === 'new') {
            $('#new-webinar-product-fields').show();
        } else {
            $('#new-webinar-product-fields').hide();
        }
    });
    
    $('#cpm-new-relationship-form').on('submit', function(e) {
        e.preventDefault();
        
        var courseId = $('#course-select').val();
        var productType = $('#product-type').val();
        
        if (!courseId || !productType) {
            alert('Please select a course and product type');
            return;
        }
        
        var data = {
            action: 'cpm_save_relationship',
            nonce: cpm_ajax.nonce,
            course_id: courseId,
            product_type: productType
        };
        
        if (productType === 'course' || productType === 'both') {
            var courseProductId = $('#course-product').val();
            data.course_product_id = courseProductId;
            
            if (courseProductId === 'new') {
                data.new_course_product_title = $('#new-course-product-title').val();
                data.new_course_product_price = $('#new-course-product-price').val();
                
                if (!data.new_course_product_title) {
                    alert('Please enter a title for the new course product');
                    return;
                }
            }
        }
        
        if (productType === 'webinar' || productType === 'both') {
            var webinarProductId = $('#webinar-product').val();
            data.webinar_product_id = webinarProductId;
            
            if (webinarProductId === 'new') {
                data.new_webinar_product_title = $('#new-webinar-product-title').val();
                data.new_webinar_product_price = $('#new-webinar-product-price').val();
                
                if (!data.new_webinar_product_title) {
                    alert('Please enter a title for the new webinar product');
                    return;
                }
            }
        }
        
        var submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: cpm_ajax.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    $('#cpm-message')
                        .removeClass('notice-error')
                        .addClass('notice-success')
                        .html('<p>' + response.data.message + '</p>')
                        .show();
                    
                    setTimeout(function() {
                        window.location.href = cpm_ajax.ajax_url.replace('admin-ajax.php', 'admin.php?page=course-product-manager');
                    }, 1500);
                } else {
                    $('#cpm-message')
                        .removeClass('notice-success')
                        .addClass('notice-error')
                        .html('<p>Error: ' + response.data + '</p>')
                        .show();
                    
                    submitButton.prop('disabled', false).text('Create Relationship');
                }
            },
            error: function() {
                $('#cpm-message')
                    .removeClass('notice-success')
                    .addClass('notice-error')
                    .html('<p>Failed to save relationship.</p>')
                    .show();
                
                submitButton.prop('disabled', false).text('Create Relationship');
            }
        });
    });
});