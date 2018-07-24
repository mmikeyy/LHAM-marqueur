import { Component, OnInit, OnDestroy } from '@angular/core';
import { Subscription } from 'rxjs';
import { NgRedux } from '@angular-redux/store';
import { MatchsService } from '../../services/matchs.service';
import { IAppState } from '../../store/store';
// import * as _ from 'lodash';
// import * as R from 'ramda';

@Component(
  {
    templateUrl: './infoMatch.component.html',
    styles: [
      `th, td{padding: .5em}`,
      `td{text-align: center}`
    ]
  }
)
export class InfoMatchComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];


  constructor(
    public store: NgRedux<IAppState>,
    public matchsServ: MatchsService
  ) {

  }

  ngOnInit() {
    // this.subs.push();
  }

  ngOnDestroy() {
    // this.subs.forEach(s => s.unsubscribe());
  }

}
