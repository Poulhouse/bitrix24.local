<!doctype html>
<html lang="ru">
<head>
	<!-- Обязательные метатеги -->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

	<title>Привет мир!</title>
	<style>
        .modal.fade.modal-right .modal-dialog {
            transform: translate(125%, 0px);
        }

        .modal.show.modal-right .modal-dialog {
            transform: none;
        }
	</style>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
</head>
<body>


<table class="table table-bordered table-hover align-middle">
	<thead class="table-light">
	<tr>
		<th>Название операции</th>
		<th>Статус интеграции
			<a href="#" class="d-inline-block" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Подсказка внизу">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-question-circle" viewBox="0 0 16 16">
					<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
					<path d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286zm1.557 5.763c0 .533.425.927 1.01.927.609 0 1.028-.394 1.028-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94"/>
				</svg>
			</a>
		</th>
		<th>Ошибки за сегодня</th>
		<th>Вызовов за сегодня</th>
		<th>Вызовов за последний месяц</th>
		<th>Вызовов за все время</th>
	</tr>
	</thead>
	<tbody>
	<tr>
		<td>
			<a href="#" class="text-primary" data-bs-toggle="modal" data-bs-target="#exampleModal">Обмен физ. лиц (1C:АК-КРЕДИТ > Б24)</a><br>
			<small class="text-secondary">ss_sync_fl_update</small>
		</td>
		<td class="text-success">все хорошо</td>
		<td class="text-decoration-underline">0</td>
		<td>315</td>
		<td>9151</td>
		<td>2025668</td>
	</tr>
	<tr>
		<td>
			<a href="#" class="text-primary" data-bs-toggle="modal" data-bs-target="#exampleModal2">Обмен Юр. лиц (1C:АК-КРЕДИТ > Б24)</a><br>
			<small class="text-secondary">ss_sync_ul_update</small>
		</td>
		<td class="text-warning">есть проблемы</td>
		<!--			<td class="text-success">все хорошо</td>-->
		<!--			<td class="text-danger">много ошибок</td>-->
		<!--			<td class="text-muted">нет вызовов</td>-->
		<td class="text-decoration-underline">2</td>
		<td>1256</td>
		<td>66259</td>
		<td>2455648</td>
	</tr>
	</tbody>
</table>
<div class="modal " id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="exampleModalLabel">Обмен физ. лиц (1C:АК-КРЕДИТ > Б24)</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
			</div>
			<div class="modal-body">
				<table class="table table-borderless align-middle">
					<thead class="table-light">
					<tr><th>Дата и время</th><th>Операция</th><th>Точка интеграции</th><th>Есть ошибки?</th><th></th></tr>
					</thead>
					<tbody>
					<tr class="text-decoration-underline">
						<td>20.12.2023 18:56:38</td>
						<td>ss_sync_fl_update</td>
						<td>1С:АК-КРЕДИТ</td>
						<td>Нет</td>
						<td>
							<div class="fs-4" data-bs-toggle="modal" data-bs-target="#exampleModal2" >
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-in-right" viewBox="0 0 16 16">
									<path fill-rule="evenodd" d="M6 3.5a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 0-1 0v2A1.5 1.5 0 0 0 6.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-8A1.5 1.5 0 0 0 5 3.5v2a.5.5 0 0 0 1 0z"></path>
									<path fill-rule="evenodd" d="M11.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L10.293 7.5H1.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"></path>
								</svg>
							</div>
						</td>
					</tr>
					<tr class="text-decoration-underline"><td>20.12.2023 18:55:21</td><td>ss_sync_fl_update</td><td>1С:АК-КРЕДИТ</td><td>Нет</td>
						<td>
							<div class="fs-4" data-bs-toggle="modal" data-bs-target="#exampleModal2"  >
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-in-right" viewBox="0 0 16 16">
									<path fill-rule="evenodd" d="M6 3.5a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 0-1 0v2A1.5 1.5 0 0 0 6.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-8A1.5 1.5 0 0 0 5 3.5v2a.5.5 0 0 0 1 0z"></path>
									<path fill-rule="evenodd" d="M11.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L10.293 7.5H1.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"></path>
								</svg>
							</div>
						</td>
					</tr>
					<tr class="table-warning text-decoration-underline" ><td>20.12.2023 18:55:08</td><td>ss_sync_fl_update</td><td>1С:АК-КРЕДИТ</td><td>Да</td>
						<td>
							<div class="fs-4" data-bs-toggle="modal" data-bs-target="#exampleModal2" >
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-in-right" viewBox="0 0 16 16">
									<path fill-rule="evenodd" d="M6 3.5a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 0-1 0v2A1.5 1.5 0 0 0 6.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-8A1.5 1.5 0 0 0 5 3.5v2a.5.5 0 0 0 1 0z"></path>
									<path fill-rule="evenodd" d="M11.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L10.293 7.5H1.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"></path>
								</svg>
							</div>
						</td>
					</tr>
					<tr class="text-decoration-underline"><td>20.12.2023 18:54:58</td><td>ss_sync_fl_update</td><td>1С:АК-КРЕДИТ</td><td>Нет</td>
						<td>
							<div class="fs-4" data-bs-toggle="modal" data-bs-target="#exampleModal2"  >
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-in-right" viewBox="0 0 16 16">
									<path fill-rule="evenodd" d="M6 3.5a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 0-1 0v2A1.5 1.5 0 0 0 6.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-8A1.5 1.5 0 0 0 5 3.5v2a.5.5 0 0 0 1 0z"></path>
									<path fill-rule="evenodd" d="M11.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L10.293 7.5H1.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"></path>
								</svg>
							</div>
						</td>
					</tr>
					<tr class="text-decoration-underline"><td>20.12.2023 18:54:24</td><td>ss_sync_fl_update</td><td>1С:АК-КРЕДИТ</td><td>Нет</td>
						<td>
							<div class="fs-4" data-bs-toggle="modal" data-bs-target="#exampleModal2"   >
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-in-right" viewBox="0 0 16 16">
									<path fill-rule="evenodd" d="M6 3.5a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 0-1 0v2A1.5 1.5 0 0 0 6.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-8A1.5 1.5 0 0 0 5 3.5v2a.5.5 0 0 0 1 0z"></path>
									<path fill-rule="evenodd" d="M11.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L10.293 7.5H1.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"></path>
								</svg>
							</div>
						</td>
					</tr>
					<tr class="text-decoration-underline"><td>20.12.2023 18:53:13</td><td>ss_sync_fl_update</td><td>1С:АК-КРЕДИТ</td><td>Нет</td>
						<td>
							<div class="fs-4" data-bs-toggle="modal" data-bs-target="#exampleModal2"   >
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-in-right" viewBox="0 0 16 16">
									<path fill-rule="evenodd" d="M6 3.5a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 0-1 0v2A1.5 1.5 0 0 0 6.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-8A1.5 1.5 0 0 0 5 3.5v2a.5.5 0 0 0 1 0z"></path>
									<path fill-rule="evenodd" d="M11.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L10.293 7.5H1.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"></path>
								</svg>
							</div>
						</td>
					</tr>
					<tr class="text-decoration-underline"><td>20.12.2023 18:50:43</td><td>ss_sync_fl_update</td><td>1С:АК-КРЕДИТ</td><td>Нет</td>
						<td>
							<div class="fs-4" data-bs-toggle="modal" data-bs-target="#exampleModal2" >
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-in-right" viewBox="0 0 16 16">
									<path fill-rule="evenodd" d="M6 3.5a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 0-1 0v2A1.5 1.5 0 0 0 6.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-8A1.5 1.5 0 0 0 5 3.5v2a.5.5 0 0 0 1 0z"></path>
									<path fill-rule="evenodd" d="M11.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L10.293 7.5H1.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"></path>
								</svg>
							</div>
						</td>
					</tr>
					<tr class="text-decoration-underline"><td>20.12.2023 18:46:12</td><td>ss_sync_fl_update</td><td>1С:АК-КРЕДИТ</td><td>Нет</td>
						<td>
							<div class="fs-4" data-bs-toggle="modal" data-bs-target="#exampleModal2"  >
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-in-right" viewBox="0 0 16 16">
									<path fill-rule="evenodd" d="M6 3.5a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 0-1 0v2A1.5 1.5 0 0 0 6.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-8A1.5 1.5 0 0 0 5 3.5v2a.5.5 0 0 0 1 0z"></path>
									<path fill-rule="evenodd" d="M11.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L10.293 7.5H1.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"></path>
								</svg>
							</div>
						</td>
					</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<div class="modal fade modal-right" id="exampleModal2" data-bs-backdrop="static" tabindex="-1" aria-labelledby="exampleModal2Label" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<!--					<table class="table table-borderless align-middle">-->
				<!--						<thead class="table-light"><tr><th>Дата и время</th><th>Операция</th><th>Точка интеграции</th><th>Есть ошибки?</th></tr></thead>
											<tbody><tr><td>20.12.2023 18:56:38</td><td>ss_sync_fl_update</td><td>1С:АК-КРЕДИТ</td><td>Нет</td></tr></tbody>-->
				<!--					</table>-->
				<h5 class="modal-title" id="exampleModal2Label">Детали выполнения операции</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
			</div>
			<div class="modal-body">
				<dl class="row">
					<dt class="col-sm-3">Дата и время</dt>
					<dd class="col-sm-9">20.12.2023 18:56:38</dd>

					<dt class="col-sm-3">Время ответа, сек.</dt>
					<dd class="col-sm-9">0,82544</dd>

					<dt class="col-sm-3">Операция</dt>
					<dd class="col-sm-9">ss_sync_fl_update</dd>

					<dt class="col-sm-3">Точка интеграции</dt>
					<dd class="col-sm-9">1С:АК-КРЕДИТ</dd>

					<dt class="col-sm-3">Есть ошибки?</dt>
					<dd class="col-sm-9">Нет</dd>

					<dt class="col-sm-3">Сообщения</dt>
					<dd class="col-sm-9">...</dd>

					<dt class="col-sm-3">Операция выполнена</dt>
					<dd class="col-sm-9">Да</dd>
				</dl>
				<div class="accordion" id="accordionExample">

					<div class="accordion-item">
						<h2 class="accordion-header" id="headingTwo">
							<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
								URL-запрос и метод
							</button>
						</h2>
						<div id="collapseTwo" class="accordion-collapse collapse show" aria-labelledby="headingTwo" data-bs-parent="#accordionExample">
							<div class="accordion-body">
								<dl class="row">
									<dt class="col-sm-3">Метод</dt>
									<dd class="col-sm-9"><code>POST</code></dd>

									<dt class="col-sm-3">URL-запрос</dt>
									<dd class="col-sm-9"><code>https://crm.sodeistvie.su/in/index.php</code></dd>
								</dl>
							</div>
						</div>
					</div>
					<div class="accordion-item">
						<h2 class="accordion-header" id="headingThree">
							<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
								Заголовки и тело запроса
							</button>
						</h2>
						<div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#accordionExample">
							<div class="accordion-body">
								<dl class="row">
									<dt class="col-sm-3">Заголовки запроса</dt>
									<dd class="col-sm-9">
										<code>
											Content-Type: application/json; charset=utf-8<br/>
											Accept: application/json<br/>
											Host: crm.sodeistvie.su
										</code>
									</dd>
									<dt class="col-sm-3">Тело запроса</dt>
									<dd class="col-sm-9"><textarea class="jsonCode" cols=50 rows=5>[{"parent1":{"chilEl1Key":"childEl1Val","chilEl2Key":"childEl2Val","childParent1Key":{"chilEl3Key":"childEl3Val","chilEl4Key":"childEl4Val"}}}]</textarea></dd>
								</dl>
							</div>
						</div>
					</div>
				</div>

			</div>
		</div>
	</div>
</div>

<!-- Дополнительный JavaScript; выберите один из двух! -->

<!-- Вариант 1: Bootstrap в связке с Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

<!-- Вариант 2: Bootstrap JS отдельно от Popper
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js" integrity="sha384-IQsoLXl5PILFhosVNubq5LC7Qb9DXgDA9i+tQ8Zj3iwWAwPtgFTxbJ8NT4GN1R8p" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js" integrity="sha384-cVKIPhGWiC2Al4u+LWgxfKTRIcfu0JTxR+EQDz/bgldoEyl4H0zUF0QKbrJ0EcQF" crossorigin="anonymous"></script>
-->
<script>
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })

    $.fn.json_beautify= function() {
        this.each(function(){
            var el = $(this),
                obj = JSON.parse(el.val()),
                pretty = JSON.stringify(obj, null, 2);
            el.text(pretty);
        });
    };

    $('textarea.jsonCode').json_beautify();
</script>
</body>
</html>