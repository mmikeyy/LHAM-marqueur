import { Component } from '@angular/core';
import { NavController } from 'ionic-angular';
import { Login } from '../login/login';

@Component({
  selector: 'page-contact',
  templateUrl: 'contact.html'
})
export class ContactPage {

  public loggedIn: boolean = false;

  constructor(public navCtrl: NavController) {

  }

  toto() {
    this.navCtrl.push(Login);
  }

}
