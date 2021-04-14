BX.ready(function () {
  const USERFIELD_NAME = "UF_CRM_CARRIER_RATE";

  var isInit = false;
  var eventSection = null;
  var tarifField = null;
  var popup = null;
  var table = null;
  var thead = null;
  var currentTarifNode = null;
  var entity_type = null;
  var entity_id = null;
  var checkedNode = null;
  var checkedData = null;
  BX.addCustomEvent('BX.Crm.EntityEditorSection:onLayout', BX.delegate(init, this));

  function init (eventSection) {
    if (isInit && BX('selectTarifButton') != null) {
      return;
    }
    tarifField = eventSection.getChildById(USERFIELD_NAME);
    if (tarifField === null) {
      return;
    }
    isInit = true;
    currentTarifNode = null;
    entity_id = eventSection._settings.editor._entityId;
    entity_type = eventSection._settings.editor._entityTypeName;
    addButton();
  }

  function addButton () {
    var fieldNode = tarifField.getWrapper();
    var titleNode = BX.findChildByClassName(fieldNode, 'ui-entity-editor-block-title', true); // ui-link ui-link-secondary ui-entity-editor-block-title-link
    var actionNode = BX.append(BX.create('div', {
      attrs: {
        className: 'crm-entity-widget-content-block-edit-action-btn',
        id: 'selectTarifButton'
      },
      events: {
        click: BX.proxy(extraPopup, this)
      },
      text: 'Выбрать тариф'
    }), titleNode);
  }

  function extraPopup (event) {
    popup = BX.PopupWindowManager.create("popup-message", event.target, {
      min_height: 50,
      width: 1100,
      height: 500,
      //zIndex: -100,
      titleBar: "Выбор тарифа для ExtraPost",
      closeByEsc: true,
      content: extraTableGenerate(),
      darkMode: false,
      autoHide: true,
      draggable: true,
      lightShadow: true,
      angle: true,
      events: {
          onPopupClose: BX.delegate(function () {
            popup.destroy();
            popup = null;
            checkedNode = null;
            checkedData = null;
          })
      },
      buttons: [
        new BX.PopupWindowButton({
            text: "Сохранить",
            id: 'save-btn',
            className: 'ui-btn ui-btn-success',
            events:
                {
                    click: BX.delegate(fillTarifData, this)
                }
        }),
        new BX.PopupWindowButton({
            text: "Закрыть",
            id: 'copy-btn',
            className: 'ui-btn ui-btn-primary',
            events: {
              click: function () {
                  this.popupWindow.close();
              }
            }
        })
      ],
    });
    thead.style.display = 'none';
    popup.show();
  }

  function extraTableGenerate () {
    table = BX.create('table', {
      'attrs' : {
        'id': 'extraPostTarifSelector',
        'className': 'table',
      }
    });
    thead = BX.create('thead');
    var tr = BX.create('tr');
    BX.append(BX.create('th', { text: "Тариф" }), tr);
    BX.append(BX.create('th', { text: "Примечание" }), tr);
    BX.append(BX.create('th', { text: "Срок доставки" }), tr);
    BX.append(BX.create('th', { text: "ПВЗ" }), tr);
    BX.append(BX.create('th', { text: "Стоимость доставки" }), tr);
    BX.append(tr,thead);
    BX.append(thead, table);
    var tbody = BX.create('tbody');
    BX.append(tbody,table);
    getTarif(tbody);
    return table;
  }

  function tBodyGenerate(bodyNode, data) {
    if (typeof(data.ERROR) !== "undefined") {
      BX.adjust(bodyNode, {
        'text' : data.ERROR
      });
      return;
    }
    table.style.display = 'table';
    thead.style.display = 'table-header-group';
    $.each(data, function(index,value) {
      var pvz = "—";
      if(typeof(value.OPS) !== 'undefined') {
        $.each(value.OPS, function (opsInd, opsVal) {
          var tr = BX.create('tr', {
            attrs: {
              'data-extra': value.dataExtra,
              'data-ops': opsVal.id,
              'data-address': opsVal.properties.address,
              'data-cash': opsVal.properties.cash_on_delivery,
              'data-term': value.delivery_days,
              'data-cost': value.rateTotal,
              'data-tarif': value.title,
            },
            events: {
              click: function (e) {
                checkedTarif(e, this)
              }
            }
          });
          // Тариф
          BX.append(BX.create('td', {
            style: {'text-align': 'left'},
            text: value.title
          }), tr);
          // Примечание
          var prim = BX.create('td');
          BX.append(prim, tr);
          BX.append(BX.create('a', {
            attrs : {
              'href': `https://yandex.ru/maps/?ll=${opsVal.geometry.coordinates[1]},${opsVal.geometry.coordinates[0]}&pt=${opsVal.geometry.coordinates[1]},${opsVal.geometry.coordinates[0]}&z=16`,
              'title': "Смотреть на Яндекс.Картах",
              'target': "_blank"
            },
            html: `<img valign="middle" src="https://static-maps.yandex.ru/1.x/?ll=${opsVal.geometry.coordinates[1]},${opsVal.geometry.coordinates[0]}&amp;z=15&amp;l=map&amp;size=350,200&amp;pt=${opsVal.geometry.coordinates[1]},${opsVal.geometry.coordinates[0]},pm2rdm">`
          }), prim)
          // Срок доставки
          BX.append(BX.create('td', {
            text: value.delivery_days + ' дн'
          }), tr);
          // ПВЗ
          BX.append(BX.create('td', {
            text: opsVal.properties.address + "\n Расстояние (" + opsVal.distance + " км)"
          }), tr);
          // Стоимость доставки
          BX.append(BX.create('td', {
            text: value.rateTotal
          }), tr);
          BX.append(tr, bodyNode);
        })
      } else {
        var tr = BX.create('tr', {
          attrs: {
            'data-extra': value.dataExtra,
            'data-term': value.delivery_days,
            'data-cost': value.rateTotal,
            'data-tarif': value.title,
          },
          events: {
            click: function (e) {
              checkedTarif(e, this)
            }
          }
        });
        // Тариф
        BX.append(BX.create('td', {
          style: {'text-align': 'left'},
          text: value.title
        }), tr);
        // Примечание
        BX.append(BX.create('td', {
        }), tr);
        // Срок доставки
        BX.append(BX.create('td', {
          text: value.delivery_days + ' дн'
        }), tr);
        //ПВЗ
        BX.append(BX.create('td', {
          text: pvz
        }), tr);
        //Стоимость доставки
        BX.append(BX.create('td', {
          text: value.rateTotal
        }), tr);
        BX.append(tr, bodyNode);
    }
    })
  }

  function getTarif(bodyNode) {
    BX.showWait();
    BX.ajax({
      url: '/ajax/belyaev.extra/select_extra_tarif.php',
      data: {
        id: entity_id,
        type: entity_type,
        action: "getEntityIndex",
        sessid: BX.bitrix_sessid(),
      },
      method: 'POST',
      dataType: 'json',
      timeout: 30,
      async: true,
      processData: true,
      scriptsRunFirst: true,
      emulateOnload: true,
      start: true,
      cache: false,
      onsuccess: function(data){
        tBodyGenerate(bodyNode, data);
        BX.closeWait();
      },
      onfailure: function(e){
        console.log(e);
        BX.closeWait();
      }
    });
  }

  function checkedTarif(event, node) {
    BX.adjust(node, {
      style: {
        "background": "#56b3f552"
      }
    });
    if(typeof(checkedNode) !== "undefined" && checkedNode != null) {
      BX.adjust(checkedNode, {
        style: {
          "background": ""
        }
      });
    }
    checkedNode = node;
    checkedData = node.dataset;
  }

  function fillTarifData() {
    if (checkedData == null) {
      popup.destroy();
      popup = null;
      return;
    }
    var valFieldTarif = checkedData.extra;
    if (typeof(checkedData.ops) !== "undefined") {
      valFieldTarif = checkedData.extra + ":" + checkedData.ops;
    }
    // Отправляем изменения
    BX.ajax.post('/ajax/belyaev.extra/select_extra_tarif.php', {
      id: entity_id,
      type: entity_type,
      action: 'updateEntity',
      sessid: BX.bitrix_sessid(),
      data: {
          "UF_CRM_CARRIER_RATE": valFieldTarif,
          "ANOTHER_FOR_AJAX": checkedData
      }
    });
    // Закрываем popup
    popup.destroy();
    popup = null;
    if (currentTarifNode == null) {
      currentTarifNode = BX.create('pre', {html: "Новое значение:<b>" + checkedData.tarif + "</b>"});
      var iframe = tarifField.getWrapper().getElementsByTagName('iframe')[0];
      BX.insertBefore(currentTarifNode, iframe);
    } else {
      currentTarifNode.innerHTML = "Новое значение:<b>" + checkedData.tarif+ "</b>";
    }
  }
})
