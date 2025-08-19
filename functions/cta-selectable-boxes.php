

/**
 * Button window selectable boxes with overlay
 */

function popup_selectable_boxes_shortcode() {
    ob_start();
    ?>
    <div style="display: flex; justify-content: center; align-items: center; margin: 0;">
        <button onclick="showPopup()" style="padding: 10px 20px; font-size: 16px; cursor: pointer; background-color: #F22EBE; border: none; border-radius: 5px;">Enroll now</button>
        <div id="overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.2); z-index: 9998;"></div>
        <div id="popup" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); font-family: Arial, sans-serif; font-size: 24px; z-index: 9999; justify-content: center;">
            <img src="https://academy.arcanalabs.ai/wp-content/uploads/2025/06/close-icon-selectable-boxes.png" alt="Close" style="position: absolute; top: -30px; right: 0px; width: 24px; height: 24px; cursor: pointer; filter: invert(100%);" onclick="closePopup()">
            <?php echo do_shortcode('[selectable_boxes]'); ?>
        </div>
    </div>
    <script>
        function showPopup() {
            const popup = document.getElementById('popup');
            const overlay = document.getElementById('overlay');
            popup.style.display = 'block';
            overlay.style.display = 'block';
            
            console.log('=== POPUP DEBUG START ===');
            console.log('Popup element:', popup);
            console.log('Overlay element:', overlay);

            // Forzar reinicialización de selectable_boxes con un retraso mayor
            setTimeout(() => {
                console.log('Popup opened, initializing enroll-course selection');
                
                // Verificar que los event listeners del carrito estén presentes
                const addToCartButtons = popup.querySelectorAll('.add-to-cart-button');
                console.log('Add to cart buttons found in popup:', addToCartButtons.length);
                
                addToCartButtons.forEach((btn, index) => {
                    console.log(`Button ${index}:`, {
                        productId: btn.getAttribute('data-product-id'),
                        disabled: btn.disabled,
                        text: btn.querySelector('.button-text')?.textContent,
                        closestBox: btn.closest('.box')?.className,
                        hasClickListeners: btn.onclick || btn._listeners || btn.getEventListeners
                    });
                });

                // Limpiar cualquier selección previa dentro del popup
                popup.querySelectorAll('.box').forEach(box => {
                    box.classList.remove('selected');
                    box.classList.add('no-button');
                    const circleContainer = box.querySelector('.circle-container');
                    const circlecontainer = box.querySelector('.circlecontainer');
                    const startDates = box.querySelector('.start-dates');
                    if (circleContainer) circleContainer.style.display = 'flex';
                    if (circlecontainer) circlecontainer.style.display = 'none';
                    if (startDates) startDates.style.display = 'none';
                    console.log('Cleared selection for box:', box.className);
                });

                // Seleccionar explícitamente la caja .enroll-course dentro del popup
                const enrollBox = popup.querySelector('.enroll-course');
                if (enrollBox) {
                    console.log('Enroll box found, applying selected state');
                    enrollBox.classList.add('selected');
                    enrollBox.classList.remove('no-button');
                    const selectedCircleContainer = enrollBox.querySelector('.circle-container');
                    const selectedCirclecontainer = enrollBox.querySelector('.circlecontainer');
                    const selectedStartDates = enrollBox.querySelector('.start-dates');
                    if (selectedCircleContainer) {
                        selectedCircleContainer.style.display = 'none';
                        console.log('Hid circle-container for enroll box');
                    }
                    if (selectedCirclecontainer) {
                        selectedCirclecontainer.style.display = 'flex';
                        console.log('Showed circlecontainer for enroll box');
                    }
                    if (selectedStartDates) {
                        selectedStartDates.style.display = 'block';
                        console.log('Showed start-dates for enroll box');
                    }
                    enrollBox.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    // Seleccionar la primera fecha por defecto
                    const firstDateBtn = enrollBox.querySelector('.date-btn');
                    if (firstDateBtn) {
                        popup.querySelectorAll('.date-btn').forEach(b => b.classList.remove('selected'));
                        firstDateBtn.classList.add('selected');
                        window.selectedDate = firstDateBtn.getAttribute('data-date') || firstDateBtn.textContent.trim();
                        console.log('Default selected date in popup:', window.selectedDate);
                    } else {
                        console.error('No date buttons found in enroll box');
                    }
                    
                    // Verificar el botón de Enroll Now
                    const enrollButton = enrollBox.querySelector('.add-to-cart-button');
                    if (enrollButton) {
                        console.log('Enroll button details:', {
                            exists: true,
                            productId: enrollButton.getAttribute('data-product-id'),
                            disabled: enrollButton.disabled,
                            text: enrollButton.querySelector('.button-text')?.textContent
                        });
                        
                        // Re-attach event listener manualmente si es necesario
                        console.log('Checking if button needs event listener reattachment...');
                        if (!enrollButton._cartListenerAttached) {
                            console.warn('⚠️ Button missing event listener! This may cause cart issues.');
                            console.log('Consider manually re-attaching the click event listener');
                        }
                    }
                } else {
                    console.error('Enroll box not found in popup');
                }
                
                console.log('=== POPUP DEBUG END ===');
                
                // Call the global popup open handler if it exists
                if (window.onPopupOpen) {
                    console.log('Calling onPopupOpen from selectable-boxes-plugin...');
                    window.onPopupOpen();
                }
            }, 300); // Aumentado a 300ms para asegurar que el DOM esté listo
        }

        function closePopup() {
            const popup = document.getElementById('popup');
            const overlay = document.getElementById('overlay');
            if (popup && overlay) {
                popup.style.display = 'none';
                overlay.style.display = 'none';
            }
        }

        document.addEventListener('click', function(event) {
            const popup = document.getElementById('popup');
            const overlay = document.getElementById('overlay');
            const button = event.target.closest('button');
            const closeIcon = event.target.closest('img[alt="Close"]');
            if (popup && overlay && popup.style.display === 'block' && !popup.contains(event.target) && !button && !closeIcon) {
                popup.style.display = 'none';
                overlay.style.display = 'none';
            }
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('popup_selectable_boxes', 'popup_selectable_boxes_shortcode');



