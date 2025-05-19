<style>
    /* Затемнение фона */
    #overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 999;
    }

    #controlPanel {
        display: flex;
        gap: 10px;
    }

    /* Popup окно */
    #filterPopup {
        display: none;
        position: fixed;
        top: 0;
        left: 50%;
        transform: translate(-50%, 0%);
        width: 90%;
        max-width: 700px;
        background-color: white;
        padding: 20px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        z-index: 1000;
        border-radius: 8px;
    }

    #filterPopup h3 {
        margin-top: 0;
    }

    #filterPopup label {
        display: block;
        width: 90%;
        margin-bottom: 5px;
    }

    #filterPopup select, #filterPopup input[type="text"] {
        width: 90%;
        padding: 5px;
        margin-bottom: 10px;
        border: 1px solid #ccc;
        border-radius: 3px;
    }
    #filterPopup .leftColumn {
        width: 50%;
        position: relative;
        float: left;
    }
    #filterPopup .rightColumn {
        width: 50%;
        position: relative;
        float: left;
    }

    #filterPopup button {
        margin-right: 10px;
        padding: 5px 10px;
    }
</style>