<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// bootstrap modal open function
function openConfirmModal(id, customerName) {
    $("#confirm_qid").val(id);

    // Correct Bootstrap 5 modal initialization
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();
}
</script>

</body>
</html>
