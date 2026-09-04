document.addEventListener("DOMContentLoaded", function () {
    const campusCards = document.querySelectorAll(".campus-card");
    let selectedCampus = null;

    campusCards.forEach(card => {
        card.addEventListener("click", function () {
            let campusId = this.dataset.campusId;

            // 🔹 Si el campus ya está seleccionado, lo deseleccionamos (volver a la universidad)
            if (selectedCampus === campusId) {
                campusId = '';
                selectedCampus = null;
            } else {
                selectedCampus = campusId;
            }

            // 🔹 Aplicar estilos para resaltar el campus seleccionado
            campusCards.forEach(c => c.classList.remove("selected-campus"));
            if (selectedCampus) {
                this.classList.add("selected-campus");
            }

            // 🔹 Llamar a la función para cargar los recursos
            fetchResources(campusId);
        });
    });

    function fetchResources(campusId = '') {
        let viewUrl = '/resources-and-services'; 
        if (campusId) {
            viewUrl += '/' + campusId; // Agrega el ID del campus si está seleccionado
        }

        fetch(viewUrl)
            .then(response => response.text())
            .then(data => {
                document.getElementById('rs-content').innerHTML = data;
            });
    }
});
