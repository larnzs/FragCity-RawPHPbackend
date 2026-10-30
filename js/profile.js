(function () {
    'use strict';

    function showToast(
        message,
        type
    ) {
        const root =
            document.querySelector(
                '.profile-fullscreen'
            );

        if (!root) return;

        const toast =
            root.querySelector('#toast');

        if (!toast) return;

        toast.textContent = message;

        toast.className =
            'toast-message show ' +
            (type || 'success');

        window.clearTimeout(
            toast._hideTimer
        );

        toast._hideTimer =
            window.setTimeout(
                function () {
                    toast.className =
                        'toast-message';
                },
                3000
            );
    }

    function setBanner(root) {
        const banner =
            root.querySelector(
                '#bannerSection'
            );

        if (!banner) return;

        const bannerUrl =
            banner.dataset.bannerUrl;

        if (bannerUrl) {
            banner.style.backgroundImage =
                'url("' +
                bannerUrl.replace(
                    /"/g,
                    '\\"'
                ) +
                '")';
        }
    }

    function setXpProgress(root) {
        const xpBars = root.querySelectorAll('.xp-bar');
        if (!xpBars.length) return;

        xpBars.forEach(function (xpBar) {
            const parsedProgress = Number(xpBar.dataset.progress || 0);
            const progress = Number.isFinite(parsedProgress)
                ? Math.max(0, Math.min(100, parsedProgress))
                : 0;

            xpBar.style.width = progress + '%';
            xpBar.setAttribute('aria-valuenow', String(progress));
        });
    }
    function closeAvatarModal(root) {
        const modal =
            root.querySelector(
                '#avatarModal'
            );

        if (!modal) return;

        modal.classList.remove(
            'is-open'
        );

        modal.setAttribute(
            'aria-hidden',
            'true'
        );

        if (modal._cropper) {
            modal._cropper.destroy();
            modal._cropper = null;
        }
    }

    function openAvatarCropper(
        root,
        file
    ) {
        const modal =
            root.querySelector(
                '#avatarModal'
            );

        const img =
            root.querySelector(
                '#cropImage'
            );

        if (
            !modal ||
            !img ||
            !window.Cropper
        ) {
            return;
        }

        const reader =
            new FileReader();

        reader.onload =
            function (event) {
                img.src =
                    event.target.result;

                modal.classList.add(
                    'is-open'
                );

                modal.setAttribute(
                    'aria-hidden',
                    'false'
                );

                if (modal._cropper) {
                    modal._cropper.destroy();
                }

                modal._cropper =
                    new Cropper(
                        img,
                        {
                            aspectRatio: 1,
                            viewMode: 1,
                            dragMode: 'move',
                            autoCropArea: 1,
                            cropBoxResizable: true,
                            background: false
                        }
                    );
            };

        reader.readAsDataURL(
            file
        );
    }

    function bindProfilePage(
        root
    ) {
        if (
            !root ||
            root.dataset
                .profileInitialized ===
                '1'
        ) {
            return;
        }

        root.dataset
            .profileInitialized = '1';

        setBanner(root);
        setXpProgress(root);

        const profileSettingsForm = root.querySelector('#profileSettingsForm');
        if (profileSettingsForm && profileSettingsForm.dataset.bound !== '1') {
            profileSettingsForm.dataset.bound = '1';
            profileSettingsForm.addEventListener('submit', function (event) {
                event.preventDefault();

                if (!profileSettingsForm.checkValidity()) {
                    profileSettingsForm.reportValidity();
                    return;
                }

                const submitButton = profileSettingsForm.querySelector('#profileSettingsSave');
                const originalButtonContent = submitButton ? submitButton.innerHTML : '';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> در حال ذخیره...';
                }

                fetch('?page=profile', {
                    method: 'POST',
                    body: new FormData(profileSettingsForm),
                    headers: {
                        'X-Profile-CSRF': root.dataset.profileCsrf || ''
                    }
                })
                    .then(async function (response) {
                        const message = await response.text();
                        if (!response.ok) {
                            throw new Error(message || 'ذخیره اطلاعات حساب ناموفق بود.');
                        }
                        return message;
                    })
                    .then(function (message) {
                        showToast(message || 'اطلاعات حساب با موفقیت ذخیره شد.', 'success');
                        window.setTimeout(function () {
                            window.location.reload();
                        }, 700);
                    })
                    .catch(function (error) {
                        showToast(error && error.message ? error.message : 'خطا در ارتباط با سرور.', 'error');
                    })
                    .finally(function () {
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.innerHTML = originalButtonContent;
                        }
                    });
            });
        }

        const uploadBannerBtn =
            root.querySelector(
                '#uploadBannerBtn'
            );

        const bannerInput =
            root.querySelector(
                '#bannerInput'
            );

        const editAvatarBtn =
            root.querySelector(
                '#editAvatarBtn'
            );

        const cropConfirmBtn =
            root.querySelector(
                '#cropConfirmBtn'
            );

        const closeAvatarModalBtn =
            root.querySelector(
                '#closeAvatarModalBtn'
            );

        const cropCancelBtn =
            root.querySelector(
                '#cropCancelBtn'
            );

        const avatarModal =
            root.querySelector(
                '#avatarModal'
            );

        if (
            uploadBannerBtn &&
            bannerInput
        ) {
            uploadBannerBtn.addEventListener(
                'click',
                function () {
                    bannerInput.click();
                }
            );

            bannerInput.addEventListener(
                'change',
                function (event) {
                    const file =
                        event.target.files &&
                        event.target.files[0];

                    if (!file) return;

                    if (
                        file.size >
                        1024 * 1024
                    ) {
                        showToast(
                            'حجم بنر نباید بیشتر از ۱ مگابایت باشد',
                            'error'
                        );

                        bannerInput.value =
                            '';

                        return;
                    }

                    const formData =
                        new FormData();

                    formData.append(
                        'banner',
                        file
                    );

                    fetch(
                        '?page=profile',
                        {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Profile-CSRF': root.dataset.profileCsrf || ''
                            }
                        }
                    )
                        .then(
                            function (
                                resp
                            ) {
                                return resp.text();
                            }
                        )
                        .then(
                            function (
                                html
                            ) {
                                if (
                                    html.includes(
                                        'بنر با موفقیت'
                                    )
                                ) {
                                    showToast(
                                        'بنر با موفقیت آپلود شد',
                                        'success'
                                    );

                                    window.setTimeout(
                                        function () {
                                            window.location.reload();
                                        },
                                        1000
                                    );
                                } else {
                                    showToast(
                                        'خطا در آپلود بنر',
                                        'error'
                                    );
                                }
                            }
                        )
                        .catch(
                            function () {
                                showToast(
                                    'خطا در ارتباط با سرور',
                                    'error'
                                );
                            }
                        );
                }
            );
        }

        if (editAvatarBtn) {
            editAvatarBtn.addEventListener(
                'click',
                function () {
                    const input =
                        document.createElement(
                            'input'
                        );

                    input.type =
                        'file';

                    input.accept =
                        'image/jpeg,image/png,image/webp';

                    input.addEventListener(
                        'change',
                        function (
                            event
                        ) {
                            const file =
                                event.target.files &&
                                event.target.files[0];

                            if (!file) {
                                return;
                            }

                            if (
                                file.size >
                                1024 * 1024
                            ) {
                                showToast(
                                    'حجم عکس نباید بیشتر از ۱ مگابایت باشد',
                                    'error'
                                );

                                return;
                            }

                            openAvatarCropper(
                                root,
                                file
                            );
                        }
                    );

                    input.click();
                }
            );
        }

        if (cropConfirmBtn) {
            cropConfirmBtn.addEventListener(
                'click',
                function () {
                    const cropper =
                        avatarModal
                            ? avatarModal._cropper
                            : null;

                    if (!cropper) {
                        return;
                    }

                    const canvas =
                        cropper.getCroppedCanvas(
                            {
                                width: 400,
                                height: 400
                            }
                        );

                    if (!canvas) {
                        return;
                    }

                    const dataURL =
                        canvas.toDataURL(
                            'image/jpeg',
                            0.9
                        );

                    fetch(
                        '?page=profile',
                        {
                            method: 'POST',
                            headers: {
                                'Content-Type':
                                    'application/x-www-form-urlencoded',
                                'X-Profile-CSRF': root.dataset.profileCsrf || ''
                            },
                            body:
                                'avatar_data=' +
                                encodeURIComponent(
                                    JSON.stringify(
                                        {
                                            img:
                                                dataURL
                                        }
                                    )
                                )
                        }
                    )
                        .then(
                            function (
                                resp
                            ) {
                                return resp.text();
                            }
                        )
                        .then(
                            function (
                                html
                            ) {
                                if (
                                    html.includes(
                                        'آواتار با موفقیت'
                                    )
                                ) {
                                    showToast(
                                        'آواتار بروزرسانی شد',
                                        'success'
                                    );

                                    window.location.reload();
                                } else {
                                    showToast(
                                        'خطا در ذخیره آواتار',
                                        'error'
                                    );
                                }
                            }
                        )
                        .catch(
                            function () {
                                showToast(
                                    'خطا در ارتباط با سرور',
                                    'error'
                                );
                            }
                        );

                    closeAvatarModal(
                        root
                    );
                }
            );
        }

        if (closeAvatarModalBtn) {
            closeAvatarModalBtn.addEventListener(
                'click',
                function () {
                    closeAvatarModal(
                        root
                    );
                }
            );
        }

        if (cropCancelBtn) {
            cropCancelBtn.addEventListener(
                'click',
                function () {
                    closeAvatarModal(
                        root
                    );
                }
            );
        }

        if (avatarModal) {
            avatarModal.addEventListener(
                'click',
                function (event) {
                    if (
                        event.target ===
                        avatarModal
                    ) {
                        closeAvatarModal(
                            root
                        );
                    }
                }
            );
        }

        window.addEventListener(
            'keydown',
            function (event) {
                if (
                    event.key ===
                        'Escape' &&
                    avatarModal &&
                    avatarModal.classList.contains(
                        'is-open'
                    )
                ) {
                    closeAvatarModal(
                        root
                    );
                }
            }
        );
    }

    function initProfilePage() {
        const root =
            document.querySelector(
                '.profile-fullscreen'
            );

        if (root) {
            bindProfilePage(root);
        }
    }

    window.initProfilePage =
        initProfilePage;

    if (
        document.readyState ===
        'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            initProfilePage,
            { once: true }
        );
    } else {
        initProfilePage();
    }

    window.addEventListener(
        'content-loaded',
        initProfilePage
    );
})();