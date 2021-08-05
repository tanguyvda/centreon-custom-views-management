/* global M */
/** @class CcmMaterial handles material design objects */

export default class CcvmMaterial {
  constructor () {
    this.instance = {};
  };

  buildModal (id) {
    const elems = document.querySelectorAll('#' + id);
    M.Modal.init(elems);
  }

  triggerModal (element, href) {
    element.attr('href', href);
    console.log('ici')
    element[0].click();
    element.removeAttr('href');
    element.removeClass('modal-trigger');
  }
}
