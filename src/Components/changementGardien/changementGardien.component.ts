import { Component, EventEmitter, OnInit, OnDestroy, Input } from '@angular/core';
import { NavController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { EditeChangementGardienComponent } from '../../pages/feuilleMatch/editeChangementGardien/editeChangementGardien.component';
import { FmService } from '../../services/fmService';
import { MatchsService } from '../../services/matchs.service';
import { IAppState } from '../../store/store';
import * as _ from 'lodash';
import * as R from 'ramda';

@Component(
  {
    selector: 'fm-changement-gardien',
    templateUrl: './changementGardien.component.html'
  }
)
export class ChangementGardienComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];

  @Input() public ev;
  @Input() editable;

  constructor(
    public store: NgRedux<IAppState>,
    public fmServ: FmService,
    public navCtrl: NavController
  ) {

  }

  ngOnInit() {
    this.subs.push();
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  click(op) {
    switch (op) {
      case 'edit': {
        this.navCtrl.push(EditeChangementGardienComponent, {ev: this.ev.toJS()});
        return;
      }
      case 'delete': {
        this.fmServ.effacerEv(this.ev);
      }
    }
  }

}
