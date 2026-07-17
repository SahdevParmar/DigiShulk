function toggleOtherType() {
    const select = document.getElementById('stall_type');
    const otherBox = document.getElementById('stall_type_other');
    if (select.value === 'Other') {
        otherBox.style.display = 'block';
        otherBox.required = true;
    } else {
        otherBox.style.display = 'none';
        otherBox.required = false;
        otherBox.value = '';
    }
}
document.addEventListener("DOMContentLoaded", () => {

    const params = new URLSearchParams(window.location.search);

    const shopId = params.get("shop");

    if (!shopId) return;

    fetch("api/get_shop.php?id=" + shopId)
        .then(res => res.json())
        .then(shop => {

            if (shop.success === false) {
                alert(shop.message);
                return;
            }

            document.getElementById("shop_id").value = shop.id;
            document.getElementById("shop_name").value = shop.shop_name;
            document.getElementById("shop_address").value = shop.address;
            document.getElementById("phone").value = shop.phone;
            document.getElementById("stall_type").value = shop.stall_type;

        })
        .catch(err => console.error(err));

});
