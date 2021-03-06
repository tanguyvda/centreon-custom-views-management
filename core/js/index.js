var modal_instance;

/**
 * Display a popup with all sharable views that the admin user has
 * @returns false if no contact is selected
 */
function shareViews() {
  let contactInfo = $("#contacts").select2('data')
  // tell to select a user
  if (contactInfo[0].id === "") {
    M.toast({html: 'You must select a contact first!', classes: 'toastError'});
    blinkSelect2($("#testing>.select2>.selection>.select2-selection"));
    return false;
  }
  
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=ListSharableViews',
    type: 'POST',
    contentType: 'application/json',
    dataType: 'JSON',
    data: JSON.stringify({
      target_user: contactInfo[0].id
    }),
    success: function(data) {
      let cvId;
      let userId;
      let locked;
      let consumed;
      let shared;
      
      $('#contact_views_modal_content').empty();
      let html = '<table class="highlight"><thead><tr><th>Name</th><th class="ccvm-centered">Share/Remove</th><th class="ccvm-centered">Lock/Unlock</th><th class="ccvm-centered">Enable/Disable display</th><th class="ccvm-centered">Grant admin rights</th></tr></thead><tbody>';
      
      $(data).each(function () {
        cvId = this.custom_view_id;
        userId = this.target_user;
        locked = this.locked;
        consumed = this.is_consumed;
        shared = this.is_share;
        owner = this.is_owner;
        
        const badge = (this.user_id === this.new_owner) ? '<span class="new badge" data-badge-caption="seized"></span>' : '';
        
        html += `<tr id="tr_cv_${cvId}"><td>${badge} ${this.name} (view id: ${this.custom_view_id})</td>` +
        `<td class="ccvm-centered">${buildShareButton(cvId, userId, shared)}</td>` +
        `<td class="ccvm-centered">${buildLockButton(cvId, userId, locked)}</td>` + 
        `<td class="ccvm-centered">${buildDisplayButton(cvId, userId, consumed)}</td>` +
        `<td class="ccvm-centered admin-column">${buildGrantAdminButton(cvId, userId, owner)}</td></tr>`;
      });
      html += '</tbody></table>'; 
      buildModal('contact_views_modal');
      $('#contact_views_modal_content').append(html);
      triggerModal($('#contact_views_modal_trigger'), "#contact_views_modal");
      buildTooltip('action');
    },
    error: function(error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  });
}

/**
 * get custom views that are shared to a user
 * @returns false if no contact is selected
 */
function getContact() {
  let contactInfo = $("#contacts").select2('data')
  // tell to select a user
  if (contactInfo[0].id === "") {
    M.toast({html: 'You must select a contact first!', classes: 'toastError'});
    blinkSelect2($("#testing>.select2>.selection>.select2-selection"));
    return false;
  }

  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=ListContactCustomViews',
    type: 'POST',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      contact_id: contactInfo[0].id
    }),
    success: function (data) {
      if (data) {
        if (modal_instance === undefined) {
          buildModal('contact_views_modal');
        }
        appendDataToModal(data, "contact_views_modal_trigger");
        triggerModal($('#contact_views_modal_trigger'), "#contact_views_modal");
      }
    },
    error: function (error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  });
}

/**
 * become owner of the selected custom view
 * @param {element} el the element that has been selected
 */
function becomeOwner(el) {
  const cvId = parseInt(el.dataset.cvid);
  const cvName = el.dataset.cvname;
  const userId = el.dataset.userid;
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=BecomeOwner',
    type: 'POST',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      custom_view_id: cvId,
      target_user: userId
    }),
    success: function (data) {
      addNewCvCard(cvName, cvId, data.contact_name)
      $("#btn_add_view_" + cvId).addClass("disabled");
    },
    error: function(error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  });
}

function buildModal (id) {
  if (modal_instance === undefined) {
    const elems = document.querySelectorAll('#' + id);
    modal_instance = M.Modal.init(elems);
  }
}

function buildTooltip (className) {
  M.Tooltip.init($('.' + className));
}

function triggerModal (element, href) {
    element.attr('href', href);
    element[0].click();
}

function appendDataToModal(data, id) {
  let instance;
  $('.info-tip').each(function () {
    instance = M.Tooltip.getInstance($(this));
    instance.destroy();
  });

  $('#contact_views_modal_content').empty()
  html = '<table class="highlight"><thead><tr><th>Name</th><th>Owner</th><th>Locked</th><th>Seize View</th></tr></thead><tbody>';

  $(data).each(function () {
    // lock design
    let lockIco = '<i class="material-icons info-tip tooltipped red-text" data-position="right" data-tooltip="view is locked">lock_outline</i>';
    if (this.locked === '0') {
      lockIco = '<i class="material-icons info-tip tooltipped green-text" data-position="right" data-tooltip="view is not locked">lock_open</i>';
    }
    
    // owner design
    let owner_ico = `<i class="material-icons info-tip tooltipped" data-position="right" data-tooltip="${this.contact_name} does not own this view" style="opacity:25%">person_pin</i>`;
    if (this.is_owner === '1') {
      owner_ico = `<i class="material-icons info-tip tooltipped" data-position="right" data-tooltip="${this.contact_name} owns this view" style="opacity:100%">person_pin</i>`;
    }

    let add_button = `<button id="btn_add_view_${this.custom_view_id}" class="btn-floating info-tip tooltipped" data-position="right" data-tooltip="Become the view owner" data-userid="${this.contact_id}" data-cvid="${this.custom_view_id}" data-cvname="${this.name}" onClick="becomeOwner(this)"><i class="material-icons">add</i></button>`;
    if (this.already_owned) {
      add_button = `<button id="btn_add_view_${this.custom_view_id}" class="btn-floating disabled" data-userid="${this.contact_id}" data-cvid="${this.custom_view_id}" data-cvname="${this.name}"><i class="material-icons">add</i></button>`;
    }

    html += `<tr><td>${this.name} (view id: ${this.custom_view_id})</td><td>${owner_ico}</td><td>${lockIco}</td><td>${add_button}</td></tr>`; //lock_open person_pin lock_outling
  });

  html += '</tbody></table>';
  $('#contact_views_modal_content').append(html);
  buildTooltip('info-tip');
}

function addNewCvCard(cvName, cvId, contactName) {
  let html = `<div id="card_${cvId}" class="col s2">` +
    '<div class="col s12">' +
    '<div class="card">' +
      '<div class="card-content white-text">' +
        `<span class="card-title">${cvName}</span>` +
        `<p>Old view owner: ${(contactName === null) ? "unknown user" : contactName}</p>` +
      '</div>' +
      '<div class="card-action">' +
        `<a href="#" data-cvid="${cvId}" data-cvname="${cvName}" onClick="giveBackOwnership(this)">Give back ownership</a>` +
      '</div>' +
    '</div>' +
  '</div>' +
'</div>';

  $("#seized_custom_views").prepend(html);
}

function removeCard(cvId) {
  $("#card_" + cvId).remove();
}

function giveBackOwnership(el) {
  const cvId = parseInt(el.dataset.cvid);
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=GiveBackOwnership',
    type: 'POST',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      custom_view_id: cvId
    }),
    success: function() {
      removeCard(cvId)
    },
    error: function(error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  })
}

function buildShareButton(cvId, userId, shared) {
  let shareIco = "screen_share";
  let shareIcoHtml = `<button id="share_button_${cvId}" data-position="right" data-cv="${cvId}" data-user="${userId}" onClick="shareToUser(this)" class="btn-floating action tooltipped `;
  let tooltipMessage = 'data-tooltip="Share view"';
  if (shared !== null) {
    shareIco = "stop_screen_share";
    shareIcoHtml += "shared red-background";
    tooltipMessage = 'data-tooltip="Withdraw view"'
  } else {
    shareIcoHtml += "green-background"
  }

  return `${shareIcoHtml}" ${tooltipMessage}><i id="i_share_${cvId}" class="material-icons">${shareIco}</i></button>`;
}

function buildLockButton(cvId, userId, locked) {
  let lockIco = "lock_open";
  let lockIcoHtml = `<button id="lock_button_${cvId}" data-position="right" data-cv="${cvId}" data-user="${userId}" onClick="lockUserView(this)" class="btn-floating action tooltipped `;
  let tooltipMessage = 'data-tooltip="Lock view"';
  if (locked === null) {
    tooltipMessage = 'data-tooltip="View not shared"';
    lockIcoHtml += 'disabled';
  } else if (locked === "0") {
    lockIco = "lock_outline";
    lockIcoHtml += 'red-background';
  } else {
    tooltipMessage = 'data-tooltip="Unlock view"';
    lockIcoHtml += 'locked green-background';
  }

  return  `${lockIcoHtml}" ${tooltipMessage}><i id="i_lock_${cvId}" class="material-icons">${lockIco}</i></button>`;
}

function buildDisplayButton(cvId, userId, consumed) {
  let displayIco = "visibility";
  let displayIcoHtml = `<button id="display_button_${cvId}" data-position="right" data-cv="${cvId}" data-user="${userId}" onClick="consumeUserView(this)" class="btn-floating action tooltipped `;
  let tooltipMessage = 'data-tooltip="Display view"';
  if (consumed === "1") {
    displayIco = "visibility_off";
    displayIcoHtml += 'consumed red-background';
    tooltipMessage = 'data-tooltip="Hide view"';
  } else if (consumed === null ) {
    displayIcoHtml += 'disabled';
  } else {
    displayIcoHtml += ' green-background'
  }
  
  return `${displayIcoHtml}" ${tooltipMessage}><i id="i_display_${cvId}" class="material-icons">${displayIco}</i></button>`;
}

function buildGrantAdminButton(cvId, userId, ownership) {
  const accountIco = "account_circle";
  let accountIcoHtml;
  let tooltipMessage;

  if (ownership === "0") {
    accountIcoHtml = `<button id="account_button_${cvId}" data-position="right" data-cv="${cvId}" data-user="${userId}" onClick="giveOwnerShip(this)" class="btn-floating action tooltipped green-background`;
    tooltipMessage = 'data-tooltip="grant admin rights"';
  } else {
    accountIcoHtml = `<button id="account_button_${cvId}" data-position="right" data-cv="${cvId}" data-user="${userId}" onClick="giveOwnerShip(this)" class="btn-floating disabled action tooltipped green-background`;
    tooltipMessage = 'data-tooltip="grant admin rights"';
  }

  return `${accountIcoHtml}" ${tooltipMessage}><i id="i_account_${cvId}" class="material-icons">${accountIco}</i></button>`;
}

function giveOwnerShip(el) {
  const cvId = parseInt(el.dataset.cv);
  const userId = parseInt(el.dataset.user);

  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=GiveOwnership',
    type: 'POST',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      custom_view_id: cvId,
      user_id: userId
    }),
    success: function() {
      disableAllbuttons(cvId);
    },
    error: function(error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  });
}

function shareToUser(el) {
  const method = ($(el).hasClass("shared")) ? 'RemoveView' : 'AddView';
  const cvId = parseInt(el.dataset.cv);
  const userId =  parseInt(el.dataset.user);
  
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=' + method,
    type: 'POST',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      custom_view_id: cvId,
      user_id: userId
    }),
    success: function() {
      let instance;
      $('.action').each(function() {
        instance = M.Tooltip.getInstance($(this));
        instance.destroy();
      })
      shareViews()
    },
    error: function(error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  });
}

function lockUserView(el) {
  if ($(el).hasClass("disabled")) {
    return false;
  }

  const toLock = ($(el).hasClass("locked")) ? 0 : 1;
  const cvId = parseInt(el.dataset.cv);
  const userId =  parseInt(el.dataset.user);
  
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=LockView',
    type: 'POST',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      custom_view_id: cvId,
      user_id: userId,
      to_lock: toLock
    }),
    success: function() {
      if (toLock === 0) {
        $("#lock_button_" + cvId).children("i").text("lock_outline");
        $("#lock_button_" + cvId).removeClass("locked");
        $("#lock_button_" + cvId).removeClass("green-background");
        $("#lock_button_" + cvId).addClass("red-background");
        updateTooltip("#lock_button_" + cvId, 'Lock view');
      } else {
        $("#lock_button_" + cvId).children("i").text("lock_open");
        $("#lock_button_" + cvId).addClass("locked");
        $("#lock_button_" + cvId).removeClass("red-background");
        $("#lock_button_" + cvId).addClass("green-background");
        updateTooltip("#lock_button_" + cvId, 'Unlock view');
      }
    },
    error: function(error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  });
}

function consumeUserView(el) {
  if ($(el).hasClass("disabled")) {
    return false;
  }

  const toConsume = ($(el).hasClass("consumed")) ? 0 : 1;
  const cvId = parseInt(el.dataset.cv);
  const userId =  parseInt(el.dataset.user);
  
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=ConsumeView',
    type: 'POST',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      custom_view_id: cvId,
      user_id: userId,
      to_consume: toConsume
    }),
    success: function() {
      if (toConsume === 0) {;
        $("#display_button_" + cvId).children("i").text("visibility");
        $("#display_button_" + cvId).removeClass("consumed");
        $("#display_button_" + cvId).removeClass("red-background");
        $("#display_button_" + cvId).addClass("green-background");
        updateTooltip('#display_button_' + cvId, 'Display view')
      } else {
        $("#display_button_" + cvId).children("i").text("visibility_off");
        $("#display_button_" + cvId).addClass("consumed");
        $("#display_button_" + cvId).removeClass("green-background");
        $("#display_button_" + cvId).addClass("red-background");
        updateTooltip('#display_button_' + cvId, 'Hide view')
      }
    },
    error: function(error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  });
}

function updateTooltip (id, message) {
  const instance = M.Tooltip.getInstance($(id));
  instance.close();
  $(id).attr('data-tooltip', message);
  instance.open();
  instance.isHovered = true;
}

function blinkSelect2 (el) {
  const originalColor = el.css("outline-color");
  let counter = 1;
  while (counter < 4) {
    el.animate({outlineColor: 'rgba(237, 28, 36, 1)'}, 600, 'easeOutCubic')
      .delay(100)
      .animate({outlineColor: originalColor}, 600, 'easeOutCubic');
    counter++;
  }
}

function disableAllbuttons(cvId) {
  let instance;
  $(`#tr_cv_${cvId}`).find('.action').each(function () {
    instance = M.Tooltip.getInstance($(this));
    instance.destroy();
    $(this).addClass('disabled');
  });
}

$(document).ready(function () {
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=ListSeizedViews',
    type: 'GET',
    contentType: 'application/json',
    success: function(data) {
      $(data).each(function () {
        addNewCvCard(this.name, this.custom_view_id, this.contact_name);
      });
    },
    error: function(error) {
      M.toast({html: error.responseJSON, classes: 'toastError'});
    }
  });
});