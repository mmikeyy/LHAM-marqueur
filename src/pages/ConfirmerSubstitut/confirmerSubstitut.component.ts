import { Component, OnInit, OnDestroy } from '@angular/core';
import { NavController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { JoueursService } from '../../services/joueursService';
import { IAppState } from '../../store/store';
import * as _ from 'lodash';
import * as R from 'ramda';

@Component(
  {
    templateUrl: './confirmerSubstitut.component.html'
  }
)
export class ConfirmerSubstitutComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];

  constructor(
    public store: NgRedux<IAppState>,
    public joueursServ: JoueursService,
    public navCtrl: NavController
  ) {

  }

  ngOnInit() {
    this.subs.push();
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  sugg(fld) {
    if (!fld) {
      return this.joueursServ.suggestionChoisie;
    }
    return this.joueursServ.suggestionChoisie[fld];
  }

  ajouter() {
    this.joueursServ.confirmerSubstitut();
    this.navCtrl.pop();
    this.navCtrl.pop();
  }
}
