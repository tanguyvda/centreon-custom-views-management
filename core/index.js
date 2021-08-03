function getContact() {
    // remove old displayed data
  $("#error").empty();
  $("#user_custom_views").empty();
  
  let contactInfo = $("#contacts").select2('data')
  // tell to select a user
  if (contactInfo[0].id === "") {
    $("#error").append("You must select a contact first");
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
        buildModal('contact_views_modal');
        appendDataToModal(data, "contact_views_modal_trigger");
        triggerModal($('#contact_views_modal_trigger'), "#contact_views_modal");
      }
    },
    error: function (error) {
      console.log(error);
    },
    fail: function(fail) {
      console.log(fail);
    }
  });
}

function displayViews(data) {
  $(data).each(function (index){
    element = `<span>${data[index].name}</span><button data-id="${data[index].custom_view_id}" onClick="becomeOwner(this)">Owner</button></br>`;
    $("#user_custom_views").append(element);
  });
}

function becomeOwner(el) {
  
  $.ajax({
    url: './api/internal.php?object=centreon_custom_views_management&action=BecomeOwner',
    type: 'POST',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      custom_view_id: parseInt(el.dataset.id)
    }),
    success: function(data) {
      console.log(data)
    },
    error: function(error) {
      console.log(error)
    }
  })
}

function buildModal (id) {
  const elems = document.querySelectorAll('#' + id);
  M.Modal.init(elems);
}

function triggerModal (element, href) {
    element.attr('href', href);
    console.log(element[0])
    element[0].click();
    // element.removeAttr('href');
    // element.removeClass('modal-trigger');
}

function appendDataToModal(data, id) {
  $('#contact_views_modal_content').empty()
  html = '<table><thead><tr><th>Name</th><th>Owner</th><th>Locked</th></tr></thead><tbody>';

  $(data).each(function (index) {
    let lock_ico = '<i class="material-icons" style="color:red">lock_outline</i>';
    let owner_ico = '<i class="material-icons" style="opacity:25%">person_pin</i>'
    if (data[index].locked === '0') {
      lock_ico = '<i class="material-icons" style="color:green">lock_open</i>';
    }
    if (data[index].is_owner === '1') {
      owner_ico = '<i class="material-icons" style="opacity:100%">person_pin</i>'
    }
    html += `<tr><td>${data[index].name}</td><td>${owner_ico}</td><td><i class="material-icons">${lock_ico}</i></td></tr>` //lock_open person_pin lock_outling
  });
  html += '</tbody></table>';
  $('#contact_views_modal_content').append(html)
}