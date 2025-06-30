/**
 * Sync Strapi courses with WordPress ACF
 */
function sync_strapi_courses($single_course_id = null) {
    // 1. Configurar la URL del API según si es un curso específico
    $url = 'https://deserving-cuddle-3fb76d5250.strapiapp.com/api/coursesv3s';
    if ($single_course_id) {
        $url .= '?filters[documentId][$eq]=' . urlencode($single_course_id);
    }

    // 2. Hacer solicitud al API de Strapi
    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => 'Bearer BEARER'
        ]
    ]);

    // 3. Verificar si la solicitud fue exitosa
    if (is_wp_error($response)) {
        error_log('Error al conectar con Strapi: ' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        error_log('Respuesta vacía desde Strapi API');
        return false;
    }

    // 4. Convertir la respuesta JSON en array
    $courses = json_decode($body, true);
    if (!isset($courses['data'])) {
        error_log('Formato de datos inválido desde Strapi API');
        return false;
    }

    // 5. Ajustar datos para un solo curso o múltiples
    $courses_data = $single_course_id && isset($courses['data'][0]) ? [$courses['data'][0]] : $courses['data'];

    // 6. Iterar sobre los cursos
    foreach ($courses_data as $course) {
        // Extraer descripción como texto plano
        $description = '';
        if (!empty($course['CourseDescription']) && is_array($course['CourseDescription'])) {
            foreach ($course['CourseDescription'] as $paragraph) {
                if (isset($paragraph['children']) && is_array($paragraph['children'])) {
                    foreach ($paragraph['children'] as $child) {
                        if (isset($child['type']) && $child['type'] === 'text' && !empty($child['text'])) {
                            $description .= wp_kses_post($child['text']) . "\n\n";
                        }
                    }
                }
            }
        }

        // Extraer biografía del instructor como texto plano
        $instructor_bio = '';
        if (!empty($course['InstructorBiography']) && is_array($course['InstructorBiography'])) {
            foreach ($course['InstructorBiography'] as $paragraph) {
                if (isset($paragraph['children']) && is_array($paragraph['children'])) {
                    foreach ($paragraph['children'] as $child) {
                        if (isset($child['type']) && $child['type'] === 'text' && !empty($child['text'])) {
                            $instructor_bio .= wp_kses_post($child['text']) . "\n\n";
                        }
                    }
                }
            }
        }

        // 7. Verificar si el curso ya existe
        $existing_posts = get_posts([
            'post_type' => 'strapicourse',
            'meta_query' => [
                [
                    'key' => 'strapi_document_id',
                    'value' => $course['documentId'],
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'post_status' => ['publish', 'draft', 'pending', 'trash']
        ]);

        if (!empty($existing_posts)) {
            // Actualizar curso existente
            $post_id = $existing_posts[0]->ID;
            wp_update_post([
                'ID' => $post_id,
                'post_title' => sanitize_text_field($course['CourseTitle']),
                'post_status' => 'publish'
            ]);
        } else {
            // Crear nuevo curso
            $post_id = wp_insert_post([
                'post_type' => 'strapicourse',
                'post_title' => sanitize_text_field($course['CourseTitle']),
                'post_status' => 'draft',
                'meta_input' => [
                    'strapi_document_id' => sanitize_text_field($course['documentId']),
                    'strapi_course_id' => sanitize_text_field($course['id'])
                ]
            ]);
        }

        // 8. Actualizar campos ACF
        update_field('field_685b06d427518', sanitize_text_field($course['CourseTitle']), $post_id); // CourseTitle
        update_field('field_685b06e527519', wp_kses_post($description), $post_id); // CourseDescription
        update_field('field_685b06f02751a', floatval($course['CourseRegularPrice']), $post_id); // CourseRegularPrice
        update_field('field_685b06fc2751b', floatval($course['CourseSalesPrice']), $post_id); // CourseSalesPrice
        update_field('field_685e164734e9d', sanitize_text_field($course['Instructor'] ?? ''), $post_id); // Instructor
        update_field('field_685e165034e9e', sanitize_text_field($course['InstructorPosition'] ?? ''), $post_id); // InstructorPosition
        update_field('field_685e165734e9f', wp_kses_post($instructor_bio), $post_id); // InstructorBiography

        // Campos no presentes en el JSON (pueden requerir manejo manual o datos adicionales)
        update_field('field_685e16b634ea1', '', $post_id); // Instructor Photo (no en JSON)
        update_field('field_685e16ec34ea2', [], $post_id); // Course Lessons (no en JSON, asumir vacío)
        update_field('field_685e175534ea5', [], $post_id); // Webinar Dates (no en JSON, asumir vacío)
        update_field('field_685e176f34ea6', [], $post_id); // Portfolio (no en JSON, asumir vacío)
    }

    return true;
}

// 9. Sincronizar al cargar el dashboard (todos los cursos)
add_action('admin_init', function() {
    if (!get_transient('strapi_sync_admin_running')) {
        set_transient('strapi_sync_admin_running', true, 60);
        sync_strapi_courses();
        delete_transient('strapi_sync_admin_running');
    }
});

// 10. Sincronizar al cargar la página de un curso en el frontend
add_action('template_redirect', function() {
    if (is_singular('strapicourse')) {
        $transient_key = 'strapi_sync_frontend_' . get_the_ID();
        if (!get_transient($transient_key)) {
            set_transient($transient_key, true, 60);
            $document_id = get_post_meta(get_the_ID(), 'strapi_document_id', true);
            if ($document_id) {
                sync_strapi_courses($document_id);
            }
            delete_transient($transient_key);
        }
    }
});
