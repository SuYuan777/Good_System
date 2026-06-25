<script>
document.addEventListener('DOMContentLoaded', function() {
    function initDateInput(el) {
        if (el.value) return;
        el.type = 'text';
        el.placeholder = 'XXXX/XX/XX';
        el.addEventListener('focus', function() { this.type = 'date'; }, {once: false});
        el.addEventListener('blur', function() {
            if (!this.value) { this.type = 'text'; this.placeholder = 'XXXX/XX/XX'; }
        });
    }
    document.querySelectorAll('input[type="date"]').forEach(initDateInput);
});
</script>
</div>
</body>
</html>