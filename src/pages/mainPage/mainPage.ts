import { Component, OnInit, OnDestroy } from '@angular/core';
import { NavController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { TranslateService } from '@ngx-translate/core';
import { NgRedux, select } from '@angular-redux/store';
import { IAppState } from '../../store/store';
import { Login } from '../login/login';
import { MatchsFuturs } from '../matchsFuturs/matchsFuturs';
import { MesMatchs } from '../mesMatchs/mesMatchs';

@Component(
  {
    templateUrl: './mainPage.html',
    styles: [
      `.logged-out{opacity: .5}`
    ]
  }
)
export class MainPage implements OnInit, OnDestroy {

  public subs: Subscription[] = [];

  @select(['pratiques', 'ressources']) $ressources;
  @select(['session', 'user', 'id_visiteur']) $idVisiteur;
  constructor(
    public store: NgRedux<IAppState>,
    public trServ: TranslateService,
    public navCtrl: NavController
  ) {

  }

  ngOnInit() {
    this.subs.push();
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  tr(tag, data = {}) {
    return this.trServ.instant(tag, data);
  }

  login() {
    this.navCtrl.push(Login);
  }
  navMatchsFuturs() {
    this.navCtrl.push(MatchsFuturs);
  }
  navMesMatchs() {
    this.navCtrl.push(MesMatchs);
  }
}
