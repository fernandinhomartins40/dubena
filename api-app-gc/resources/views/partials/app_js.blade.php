<script type="text/javascript">
    const helpersAttributes = {
        root: '{{url("/")}}',
        routeName: "{{ Route::currentRouteName() }}"
    };
    document.addEventListener("DOMContentLoaded", function () {
        let submenu = document.getElementsByClassName("submenu");
        for (let i = 0; i < submenu.length; i++) {
            submenu[i].addEventListener('click', function (e) {
                if (this.getAttribute("aria-expanded") === "false") {
                    e.stopPropagation();
                }
            }, false);
        }
    });
</script>