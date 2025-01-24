<div class="intranet-user-profile-column-block">
				<div class="intranet-user-profile-column-block-title">
					<span class="intranet-user-profile-column-block-title-text"><?=Loc::getMessage('INTRANET_USER_PROFILE_BLOG_GRAT_TITLE')?></span><?
					if (
						!empty($arResult["Gratitudes"]['URL_ADD'])
						&& $USER->getId() != $arResult["User"]["ID"]
					)
					{
						?><div  onclick="BX.SidePanel.Instance.open('<?=$arResult['Gratitudes']['URL_ADD']?>', {
							cacheable: false,
							data: {
								entityType: 'gratPost',
								entityId: '<?=intval($arResult["User"]["ID"])?>'
							},
							width: 1000
						}); return event.preventDefault();" class="intranet-user-profile-column-block-title-like" data-role="intranet-user-profile-column-block-title-like"><?=Loc::getMessage('INTRANET_USER_PROFILE_BLOG_GRAT_ADD')?></div><?
					}
				?></div>

				<div id="intranet-user-profile-thanks" class="intranet-user-profile-thanks" data-bx-grat-url="<?=htmlspecialcharsbx($arResult['Gratitudes']['URL_LIST'])?>"><?
					foreach($arResult['Gratitudes']['BADGES'] as $badge)
					{
						?><div class="intranet-user-profile-thanks-item intranet-user-profile-thanks-item-<?=htmlspecialcharsbx($badge['CODE'])?>" title="<?=htmlspecialcharsbx($badge['NAME'])?>" data-bx-grat-code="<?=htmlspecialcharsbx($badge['CODE'])?>" data-bx-grat-enum="<?=intval($badge['ID'])?>"></div><?
					}
				?></div>

				<div class="intranet-user-profile-thanks-users">
					<div class="intranet-user-profile-thanks-users-wrapper" id="intranet-user-profile-thanks-users-wrapper"></div>
					<div class="intranet-user-profile-thanks-users-loader" id="intranet-user-profile-thanks-users-loader"></div>
					<div class="intranet-user-profile-load-users-link" style="display: none;" id="intranet-user-profile-load-users-link"><?=Loc::getMessage('INTRANET_USER_PROFILE_MORE', array('#NUM#' => $arParams['GRAT_POST_LIST_PAGE_SIZE']))?></div>
				</div><?

				if (
					!empty($arResult["Gratitudes"]['URL_ADD'])
					&& $USER->getId() != $arResult["User"]["ID"]
				)
				{
					?><div class="intranet-user-profile-thanks-info">
						<a href="javascript:void(0);" onclick="BX.SidePanel.Instance.open('<?=$arResult['Gratitudes']['URL_ADD']?>', {
							cacheable: false,
							data: {
								entityType: 'gratPost',
								entityId: '<?=intval($arResult["User"]["ID"])?>'
							},
							width: 1000
						}); return event.preventDefault();" class="ui-btn ui-btn-xs ui-btn-light-border ui-btn-round"><?=Loc::getMessage('INTRANET_USER_PROFILE_BLOG_GRAT_ADD')?></a>
					</div><?
				}
			?></div>